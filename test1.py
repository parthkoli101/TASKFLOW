import sys
import os
import json
import re
import ast
from pathlib import Path
from collections import defaultdict

def lazy_import_lizard():
    """Import lizard only when needed"""
    try:
        import lizard
        return lizard
    except ImportError:
        print("Error: 'lizard' library not found. Install with: pip install lizard")
        sys.exit(1)

def read_code_file(file_path):
    """Read code file with multiple encoding attempts"""
    encodings = ['utf-8', 'latin-1', 'cp1252', 'iso-8859-1']
    
    for encoding in encodings:
        try:
            with open(file_path, 'r', encoding=encoding) as f:
                return f.read()
        except (UnicodeDecodeError, LookupError):
            continue
    
    return ""

def detect_language(file_path):
    """Detect programming language from file extension"""
    ext_map = {
        '.py': 'python',
        '.java': 'java',
        '.js': 'javascript',
        '.ts': 'typescript',
        '.c': 'c',
        '.cpp': 'cpp',
        '.cc': 'cpp',
        '.cxx': 'cpp',
        '.cs': 'csharp',
        '.go': 'go',
        '.rs': 'rust',
        '.rb': 'ruby',
        '.php': 'php',
        '.swift': 'swift',
        '.kt': 'kotlin',
        '.m': 'objectivec',
        '.scala': 'scala',
        '.lua': 'lua',
        '.r': 'r',
        '.pl': 'perl',
        '.sh': 'shell',
        '.sql': 'sql'
    }
    
    ext = Path(file_path).suffix.lower()
    return ext_map.get(ext, 'unknown')

class PythonASTAnalyzer(ast.NodeVisitor):
    """Precise Python complexity analysis using AST"""
    
    def __init__(self):
        self.max_loop_depth = 0
        self.current_loop_depth = 0
        self.has_recursion = False
        self.function_calls = defaultdict(list)
        self.current_function = None
        self.array_dimensions = {}
        self.sorting_detected = False
        self.binary_search_detected = False
        self.dynamic_prog = False
        
    def visit_FunctionDef(self, node):
        old_function = self.current_function
        self.current_function = node.name
        
        # Check for recursion
        for child in ast.walk(node):
            if isinstance(child, ast.Call):
                if isinstance(child.func, ast.Name) and child.func.id == node.name:
                    self.has_recursion = True
        
        self.generic_visit(node)
        self.current_function = old_function
    
    def visit_For(self, node):
        self.current_loop_depth += 1
        self.max_loop_depth = max(self.max_loop_depth, self.current_loop_depth)
        self.generic_visit(node)
        self.current_loop_depth -= 1
    
    def visit_While(self, node):
        self.current_loop_depth += 1
        self.max_loop_depth = max(self.max_loop_depth, self.current_loop_depth)
        self.generic_visit(node)
        self.current_loop_depth -= 1
    
    def visit_Call(self, node):
        # Detect sorting
        if isinstance(node.func, ast.Attribute):
            if node.func.attr in ['sort', 'sorted']:
                self.sorting_detected = True
        elif isinstance(node.func, ast.Name):
            if node.func.id in ['sorted', 'heapq', 'bisect']:
                self.sorting_detected = True
            if 'search' in node.func.id.lower():
                self.binary_search_detected = True
        
        self.generic_visit(node)
    
    def visit_ListComp(self, node):
        # List comprehensions add loop depth
        self.current_loop_depth += len(node.generators)
        self.max_loop_depth = max(self.max_loop_depth, self.current_loop_depth)
        self.generic_visit(node)
        self.current_loop_depth -= len(node.generators)
    
    def visit_Assign(self, node):
        # Detect array allocations
        if isinstance(node.value, ast.List):
            self._check_list_dimensions(node.value, node.targets)
        self.generic_visit(node)
    
    def _check_list_dimensions(self, node, targets, depth=1):
        if isinstance(node, ast.List) and node.elts:
            if isinstance(node.elts[0], ast.List):
                self._check_list_dimensions(node.elts[0], targets, depth + 1)
            else:
                for target in targets:
                    if isinstance(target, ast.Name):
                        self.array_dimensions[target.id] = depth

def analyze_python_ast(code_text):
    """Use AST for precise Python analysis"""
    try:
        tree = ast.parse(code_text)
        analyzer = PythonASTAnalyzer()
        analyzer.visit(tree)
        
        return {
            'max_depth': analyzer.max_loop_depth,
            'has_recursion': analyzer.has_recursion,
            'has_sorting': analyzer.sorting_detected,
            'has_binary_search': analyzer.binary_search_detected,
            'max_array_dim': max(analyzer.array_dimensions.values()) if analyzer.array_dimensions else 0
        }
    except:
        return None

def analyze_loop_structure(code_text, language):
    """
    Advanced loop analysis with pattern recognition
    Handles nested loops, loop variables, and dependencies
    """
    # Remove comments and strings
    if language in ['java', 'c', 'cpp', 'csharp', 'javascript', 'typescript', 'go', 'rust']:
        code_cleaned = re.sub(r'//.*?$|/\*.*?\*/|"(?:\\.|[^"\\])*"|\'(?:\\.|[^\'\\])*\'', '', 
                              code_text, flags=re.MULTILINE | re.DOTALL)
    elif language == 'python':
        code_cleaned = re.sub(r'#.*?$|""".*?"""|\'\'\'.*?\'\'\'|"(?:\\.|[^"\\])*"|\'(?:\\.|[^\'\\])*\'', '', 
                              code_text, flags=re.MULTILINE | re.DOTALL)
    else:
        code_cleaned = code_text
    
    # Detect recursion patterns
    recursion_patterns = [
        r'return\s+\w+\s*\([^)]*\w+\s*[-+]\s*\d+[^)]*\)',  # return func(n-1)
        r'return\s+\w+\s*\([^)]*\w+\s*/\s*\d+[^)]*\)',     # return func(n/2)
        r'\w+\s*\(\s*\w+\s*/\s*2\s*\)',                     # func(n/2) - divide & conquer
    ]
    
    has_recursion = any(re.search(pattern, code_cleaned) for pattern in recursion_patterns)
    is_divide_conquer = bool(re.search(r'\w+\s*\(\s*\w+\s*/\s*2\s*\)', code_cleaned))
    
    # Detect sorting and searching
    has_sorting = bool(re.search(r'\b(sort|Sort|qsort|mergeSort|quickSort|heapSort)\s*\(', code_cleaned))
    has_binary_search = bool(re.search(r'\b(binarySearch|binary_search|Collections\.binarySearch)\s*\(', code_cleaned))
    
    # Advanced loop detection
    lines = code_cleaned.split('\n')
    loop_stack = []
    max_depth = 0
    loop_vars = {}
    dependent_loops = 0
    
    for i, line in enumerate(lines):
        stripped = line.strip()
        indent = len(line) - len(line.lstrip())
        
        # Detect loop start
        loop_match = None
        if language in ['java', 'c', 'cpp', 'csharp', 'javascript', 'typescript']:
            loop_match = re.search(r'\b(for|while)\s*\(\s*([^;)]+)', stripped)
        elif language == 'python':
            loop_match = re.search(r'\b(for|while)\s+(\w+)', stripped)
        elif language in ['go', 'rust', 'swift', 'kotlin']:
            loop_match = re.search(r'\b(for|while)\s+([^{]+)', stripped)
        
        if loop_match:
            loop_var = loop_match.group(2).split()[0] if loop_match.group(2) else ''
            loop_stack.append((indent, loop_var, i))
            max_depth = max(max_depth, len(loop_stack))
            
            # Check if loop variable depends on outer loop
            if len(loop_stack) > 1:
                for _, outer_var, _ in loop_stack[:-1]:
                    if outer_var and outer_var in stripped:
                        dependent_loops += 1
                        break
        
        # Detect loop end (closing brace or dedent)
        if '}' in stripped or (language == 'python' and loop_stack and indent <= loop_stack[-1][0]):
            while loop_stack and ('}' in stripped or indent <= loop_stack[-1][0]):
                loop_stack.pop() if loop_stack else None
                if not stripped or stripped.startswith(('def ', 'class ')):
                    break
    
    return {
        'max_depth': max_depth,
        'has_recursion': has_recursion,
        'is_divide_conquer': is_divide_conquer,
        'has_sorting': has_sorting,
        'has_binary_search': has_binary_search,
        'dependent_loops': dependent_loops
    }

def analyze_space_complexity(code_text, language):
    """
    Analyze memory allocation patterns
    """
    space_score = 0
    
    # Multi-dimensional array patterns
    patterns = {
        '3d_array': [
            r'\[\s*\d*\s*\]\s*\[\s*\d*\s*\]\s*\[\s*\d*\s*\]',
            r'new\s+\w+\s*\[\s*\w+\s*\]\s*\[\s*\w+\s*\]\s*\[\s*\w+\s*\]',
        ],
        '2d_array': [
            r'\[\s*\d*\s*\]\s*\[\s*\d*\s*\]',
            r'new\s+\w+\s*\[\s*\w+\s*\]\s*\[\s*\w+\s*\]',
            r'\[\s*\[\s*.*?\s*\]\s*for\s+.*?\s*in\s+.*?\s*\]',  # Python list comp
        ],
        'dynamic_alloc': [
            r'\bnew\s+\w+\s*\[',
            r'\bmalloc\s*\(',
            r'\bArrayList\b|\bVector\b|\bHashMap\b|\bHashSet\b',
            r'\blist\s*\(|\bdict\s*\(|\bset\s*\(',
        ],
        'recursion_stack': [
            r'return\s+\w+\s*\(',
        ]
    }
    
    if any(re.search(p, code_text) for p in patterns['3d_array']):
        space_score = 3
    elif any(re.search(p, code_text) for p in patterns['2d_array']):
        space_score = 2
    elif any(re.search(p, code_text) for p in patterns['dynamic_alloc']):
        space_score = 1
    
    # Recursion adds call stack
    if any(re.search(p, code_text) for p in patterns['recursion_stack']):
        space_score = max(space_score, 1)
    
    return space_score

def calculate_time_complexity(loop_analysis, python_ast=None):
    """
    Calculate time complexity using multiple signals
    """
    # Use Python AST if available
    if python_ast:
        max_depth = python_ast['max_depth']
        has_recursion = python_ast['has_recursion']
        has_sorting = python_ast['has_sorting']
    else:
        max_depth = loop_analysis['max_depth']
        has_recursion = loop_analysis['has_recursion']
        has_sorting = loop_analysis['has_sorting']
    
    # Recursion analysis
    if has_recursion:
        if loop_analysis.get('is_divide_conquer'):
            return "O(n log n)", "Divide and Conquer"
        elif max_depth > 0:
            # Recursion + loops
            return "O(2^n)", "Exponential (Recursion + Loops)"
        else:
            return "O(2^n)", "Exponential (Recursion)"
    
    # Sorting detected
    if has_sorting or (python_ast and python_ast['has_sorting']):
        if max_depth >= 2:
            return "O(n^2 log n)", "Nested Loops with Sorting"
        elif max_depth == 1:
            return "O(n log n)", "Linear with Sorting"
        else:
            return "O(n log n)", "Sorting Operation"
    
    # Binary search
    if loop_analysis.get('has_binary_search') or (python_ast and python_ast.get('has_binary_search')):
        return "O(log n)", "Binary Search"
    
    # Loop depth analysis
    if max_depth == 0:
        return "O(1)", "Constant"
    elif max_depth == 1:
        return "O(n)", "Linear"
    elif max_depth == 2:
        return "O(n^2)", "Quadratic"
    elif max_depth == 3:
        return "O(n^3)", "Cubic"
    elif max_depth == 4:
        return "O(n^4)", "Quartic"
    else:
        return f"O(n^{max_depth})", f"Polynomial (degree {max_depth})"

def calculate_space_complexity(space_score, loop_analysis, python_ast=None):
    """
    Calculate space complexity from allocation patterns
    """
    # Check Python AST for array dimensions
    if python_ast and python_ast['max_array_dim'] > 0:
        space_score = max(space_score, python_ast['max_array_dim'])
    
    # Add recursion depth
    has_recursion = python_ast['has_recursion'] if python_ast else loop_analysis['has_recursion']
    
    if space_score >= 3:
        complexity = "O(n^3)"
    elif space_score == 2:
        complexity = "O(n^2)"
    elif space_score >= 1 or has_recursion:
        complexity = "O(n)"
    else:
        complexity = "O(1)"
    
    return complexity

def calculate_code_cost(avg_ccn, nloc, max_depth, space_score):
    """
    Enhanced code cost calculation
    """
    # CCN weight: 30%
    ccn_score = min(30, avg_ccn * 3)
    
    # NLOC weight: 25%
    nloc_score = min(25, nloc * 0.1)
    
    # Loop depth weight: 30%
    depth_score = min(30, max_depth * 10)
    
    # Space complexity weight: 15%
    space_cost = min(15, space_score * 5)
    
    total = int(ccn_score + nloc_score + depth_score + space_cost)
    return min(100, total)

def analyze_with_lizard(file_path, code_text):
    """Analyze code using Lizard library"""
    lizard = lazy_import_lizard()
    
    try:
        analysis = lizard.analyze_file.analyze_source_code(file_path, code_text)
        
        if not analysis.function_list:
            return {
                'avg_ccn': 1,
                'max_ccn': 1,
                'nloc': len([l for l in code_text.split('\n') if l.strip()]),
                'avg_params': 0,
                'func_count': 0,
                'avg_tokens': 0,
                'top_functions': []
            }
        
        ccn_values = [f.cyclomatic_complexity for f in analysis.function_list]
        param_values = [f.parameter_count for f in analysis.function_list]
        token_values = [f.token_count for f in analysis.function_list]
        
        sorted_funcs = sorted(
            analysis.function_list,
            key=lambda f: f.cyclomatic_complexity,
            reverse=True
        )[:10]
        
        top_functions = [
            {
                'name': f.name,
                'ccn': f.cyclomatic_complexity,
                'nloc': f.nloc,
                'params': f.parameter_count,
                'tokens': f.token_count
            }
            for f in sorted_funcs
        ]
        
        return {
            'avg_ccn': sum(ccn_values) / len(ccn_values),
            'max_ccn': max(ccn_values),
            'nloc': analysis.nloc,
            'avg_params': sum(param_values) / len(param_values),
            'func_count': len(analysis.function_list),
            'avg_tokens': sum(token_values) / len(token_values),
            'top_functions': top_functions
        }
    
    except Exception as e:
        print(f"Error: Lizard analysis failed - {str(e)}")
        sys.exit(1)

def main():
    if len(sys.argv) < 3:
        print("Usage: python complexity_analyzer.py <task_id> <file_path> [--json]")
        sys.exit(1)
    
    task_id = sys.argv[1]
    file_path = sys.argv[2]
    output_json = '--json' in sys.argv
    
    if not os.path.exists(file_path):
        print("Error: File not found")
        sys.exit(1)
    
    code_text = read_code_file(file_path)
    if not code_text:
        print("Error: Unable to read file or file is empty")
        sys.exit(1)
    
    language = detect_language(file_path)
    
    # Lizard metrics
    metrics = analyze_with_lizard(file_path, code_text)
    
    # Python AST analysis (if Python file)
    python_ast = None
    if language == 'python':
        python_ast = analyze_python_ast(code_text)
    
    # Loop structure analysis
    loop_analysis = analyze_loop_structure(code_text, language)
    
    # Space complexity analysis
    space_score = analyze_space_complexity(code_text, language)
    
    # Calculate complexities
    time_complexity, complexity_class = calculate_time_complexity(loop_analysis, python_ast)
    space_complexity = calculate_space_complexity(space_score, loop_analysis, python_ast)
    
    # Calculate code cost
    max_depth = python_ast['max_depth'] if python_ast else loop_analysis['max_depth']
    code_cost = calculate_code_cost(metrics['avg_ccn'], metrics['nloc'], max_depth, space_score)
    
    # Determine complexity level
    if code_cost <= 20:
        level = "Low"
    elif code_cost <= 40:
        level = "Moderate"
    elif code_cost <= 60:
        level = "High"
    else:
        level = "Very High"
    
    # Output
    if output_json:
        result = {
            'task_id': task_id,
            'language': language,
            'time_complexity': time_complexity,
            'space_complexity': space_complexity,
            'code_cost': code_cost,
            'complexity_level': level,
            'cyclomatic_complexity': round(metrics['avg_ccn'], 2),
            'max_cyclomatic_complexity': metrics['max_ccn'],
            'lines_of_code': metrics['nloc'],
            'function_count': metrics['func_count'],
            'avg_parameters': round(metrics['avg_params'], 2),
            'avg_tokens': round(metrics['avg_tokens'], 2),
            'complexity_class': complexity_class,
            'loop_nesting_depth': max_depth,
            'has_recursion': loop_analysis['has_recursion'],
            'has_sorting': loop_analysis.get('has_sorting', False),
            'top_complex_functions': metrics['top_functions']
        }
        
        print(json.dumps(result, indent=2))
    else:
        print(f"time_complexity: {time_complexity}")
        print(f"space_complexity: {space_complexity}")
        print(f"code_cost: {code_cost}")
        print(f"complexity_level: {level}")
        print(f"cyclomatic_complexity: {round(metrics['avg_ccn'], 2)}")
        print(f"max_cyclomatic_complexity: {metrics['max_ccn']}")
        print(f"lines_of_code: {metrics['nloc']}")
        print(f"function_count: {metrics['func_count']}")
        print(f"loop_nesting_depth: {max_depth}")
        print(f"complexity_class: {complexity_class}")

if __name__ == "__main__":
    main()