import sys
import json
import warnings
warnings.filterwarnings('ignore')

try:
    import PyPDF2
    import language_tool_python
    import os
except ImportError as e:
    print(json.dumps({"success": False, "error": f"Missing library: {str(e)}"}))
    sys.exit(1)

def extract_text_from_pdf(pdf_path):
    text = ""
    try:
        if not os.path.exists(pdf_path):
            raise Exception(f"PDF file not found: {pdf_path}")
        
        with open(pdf_path, 'rb') as file:
            reader = PyPDF2.PdfReader(file)
            num_pages = len(reader.pages)
            
            if num_pages == 0:
                raise Exception("PDF has no pages")
            
            for page in reader.pages:
                page_text = page.extract_text()
                if page_text:
                    text += page_text + "\n"
    except Exception as e:
        raise Exception(f"Error reading PDF: {str(e)}")
    return text

def check_grammar(text):
    tool = None
    try:
        tool = language_tool_python.LanguageTool('en-US')
        matches = tool.check(text)
        errors = []
        for match in matches:
            error_context = match.context
            error_offset = match.offsetInContext
            error_length = match.errorLength
            
            before_text = error_context[:error_offset]
            error_text = error_context[error_offset:error_offset + error_length]
            after_text = error_context[error_offset + error_length:]
            
            highlighted_context = before_text + "[" + error_text + "]" + after_text
            
            errors.append({
                "error_message": match.message,
                "context": highlighted_context.strip(),
                "incorrect_text": error_text,
                "suggestions": match.replacements[:3] if match.replacements else [],
                "rule": match.ruleId
            })
        return errors
    except Exception as e:
        raise Exception(f"Grammar check error: {str(e)}")
    finally:
        if tool is not None:
            try:
                tool.close()
            except:
                pass

if __name__ == "__main__":
    try:
        if len(sys.argv) != 2:
            result = {"success": False, "error": "No file path provided"}
            print(json.dumps(result))
            sys.exit(1)
        
        pdf_path = sys.argv[1]
        
        text = extract_text_from_pdf(pdf_path)
        
        if not text.strip():
            result = {
                "success": True,
                "total_errors": 0,
                "errors": [],
                "message": "No text found in PDF"
            }
            print(json.dumps(result))
            sys.exit(0)
        
        errors = check_grammar(text)
        
        result = {
            "success": True,
            "total_errors": len(errors),
            "errors": errors
        }
        
        print(json.dumps(result, ensure_ascii=False))
        sys.stdout.flush()
        
    except Exception as e:
        result = {"success": False, "error": str(e)}
        print(json.dumps(result))
        sys.stdout.flush()
        sys.exit(1)