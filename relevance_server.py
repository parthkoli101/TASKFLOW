import PyPDF2
import string
import sys
import os
import re
import json
import language_tool_python
from better_profanity import profanity
import nltk
from nltk.tokenize import word_tokenize
from nltk.corpus import stopwords
from collections import Counter
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from functools import lru_cache
import concurrent.futures
import threading
from flask import Flask, request, jsonify
import time

# Disable parallel tokenizers warning
os.environ['TOKENIZERS_PARALLELISM'] = 'false'

print("DEBUG: Pre-loading all services...", file=sys.stderr)

# Download NLTK data if not present
try:
    nltk.data.find('tokenizers/punkt')
except LookupError:
    nltk.download('punkt', quiet=True)

try:
    nltk.data.find('corpora/stopwords')
except LookupError:
    nltk.download('stopwords', quiet=True)

print("DEBUG: Loading BERT model...", file=sys.stderr)

BERT_LOADED = False
model = None
try:
    from sentence_transformers import SentenceTransformer
    model = SentenceTransformer('all-MiniLM-L6-v2')
    BERT_LOADED = True
    print("DEBUG: ✅ BERT loaded successfully", file=sys.stderr)
    
    # 🔥 CRITICAL: WARM-UP BERT MODEL 🔥
    print("DEBUG: Warming up BERT model...", file=sys.stderr)
    warmup_texts = [
        "This is a warmup text for BERT model initialization.",
        "Another warmup text to ensure consistent embeddings.",
        "Final warmup to stabilize the model parameters."
    ]
    warmup_embeddings = model.encode(warmup_texts, show_progress_bar=False, batch_size=2)
    print("DEBUG: ✅ BERT model warmed up successfully", file=sys.stderr)
    
except Exception as e:
    print(f"DEBUG: ❌ BERT loading failed: {e}", file=sys.stderr)

print("DEBUG: Loading profanity filter...", file=sys.stderr)
profanity.load_censor_words()

print("DEBUG: Loading grammar tool...", file=sys.stderr)
grammar_tool = language_tool_python.LanguageTool('en-US')
grammar_lock = threading.Lock()

print("DEBUG: ✅ All services loaded successfully!", file=sys.stderr)

app = Flask(__name__)

# Global variables for caching
STOP_WORDS = set(stopwords.words('english'))

def extract_text_from_pdf(pdf_path):
    """Fast PDF text extraction"""
    try:
        with open(pdf_path, 'rb') as file:
            reader = PyPDF2.PdfReader(file)
            text_chunks = []
            for page in reader.pages:
                text = page.extract_text()
                if text and len(text.strip()) > 0:
                    text_chunks.append(text)
            return ' '.join(text_chunks)
    except Exception as e:
        print(f"ERROR: PDF extraction failed: {e}", file=sys.stderr)
        return ""

def detect_grammatical_errors(text):
    if not text or len(text.strip()) < 10:
        return 0, []
    
    text_sample = text[:1500]
    
    try:
        with grammar_lock:
            matches = grammar_tool.check(text_sample)
        
        error_count = len(matches)
        error_types = list(set([match.ruleId for match in matches]))[:5]
        
        return error_count, error_types
        
    except Exception as e:
        print(f"DEBUG: Grammar check error: {e}", file=sys.stderr)
        return 0, []

def detect_random_text(text):
    words = text.split()
    if len(words) < 25:
        return False, 0
    
    word_freq = Counter(word for word in words if len(word) > 3)
    
    if not word_freq:
        return False, 0
    
    max_repetition = word_freq.most_common(1)[0][1]
    repetition_ratio = max_repetition / len(words)
    
    blah_count = len(re.findall(r'\b(?:blah|bla|blablabla|random|text|content|asdf|qwerty)\b', text, re.IGNORECASE))
    has_blah_pattern = blah_count > 5
    
    is_random = repetition_ratio > 0.5 or has_blah_pattern
    confidence = int(min(100, repetition_ratio * 100 + blah_count * 10))
    
    return is_random, confidence

def contains_bad_words(text):
    if not text or len(text.strip()) < 5:
        return False, []
    
    try:
        if profanity.contains_profanity(text):
            censored_text = profanity.censor(text)
            original_words = text.split()
            censored_words = censored_text.split()
            
            found_bad_words = [
                orig.lower() for orig, cens in zip(original_words, censored_words)
                if '****' in cens and len(orig) > 2
            ]
            
            found_bad_words = list(set(found_bad_words))
            return len(found_bad_words) > 0, found_bad_words
        
        return False, []
        
    except Exception as e:
        print(f"DEBUG: Bad words detection error: {e}", file=sys.stderr)
        return False, []

@lru_cache(maxsize=512)
def get_filtered_words(text):
    clean_text = re.sub(r'[^\w\s]', '', text.lower())
    return set(w for w in clean_text.split() if w and len(w) > 2 and w not in STOP_WORDS)

def calculate_fallback_semantic(submitted_text, task_context):
    if not task_context or not submitted_text:
        return 0
    
    task_words = get_filtered_words(task_context)
    submitted_words = get_filtered_words(submitted_text)
    
    if not task_words:
        return 0
    
    intersection = task_words & submitted_words
    union = task_words | submitted_words
    
    if not union:
        return 0
    
    similarity = len(intersection) / len(union)
    semantic_score = float(similarity * 100)
    
    return semantic_score

def calculate_semantic_relevance_stable(submitted_text, title, description, keywords):
    """STABLE semantic scoring with warmed-up BERT"""
    task_parts = [p.strip() for p in [title, description] if p and p.strip()]
    if keywords:
        task_parts.append(" ".join(keywords))
    
    task_context = " ".join(task_parts).strip()
    
    if not task_context or len(task_context) < 3:
        return 0
        
    if not submitted_text or len(submitted_text) < 10:
        return 0
    
    submitted_sample = submitted_text[:2000]
    
    # Try BERT first (now warmed up and stable)
    if BERT_LOADED and model is not None:
        try:
            # Use same parameters every time for consistency
            embeddings = model.encode(
                [task_context, submitted_sample], 
                show_progress_bar=False, 
                batch_size=2,
                normalize_embeddings=True  # Important for consistency
            )
            similarity = cosine_similarity([embeddings[0]], [embeddings[1]])[0][0]
            semantic_score = float(similarity * 100)
            return semantic_score
        except Exception as e:
            print(f"DEBUG: BERT failed, using TF-IDF: {e}", file=sys.stderr)
    
    # Fallback to TF-IDF
    try:
        vectorizer = TfidfVectorizer(stop_words='english', max_features=300, ngram_range=(1, 2))
        tfidf_matrix = vectorizer.fit_transform([task_context, submitted_sample])
        similarity = cosine_similarity(tfidf_matrix[0:1], tfidf_matrix[1:2])[0][0]
        semantic_score = float(similarity * 100)
        return semantic_score
    except Exception:
        return 0

def keyword_relevance_score(pdf_text, keywords):
    pdf_text_clean = pdf_text.lower().translate(str.maketrans('', '', string.punctuation))
    pdf_text_clean = pdf_text_clean.replace('_', ' ').replace('\n', ' ')
    
    pdf_word_set = set(pdf_text_clean.split())
    
    if not keywords:
        return 100, [], []
    
    found_keywords = []
    missing_keywords = []
    total_score = 0
    
    for keyword in keywords:
        keyword_clean = keyword.lower().replace('_', ' ').strip()
        
        if keyword_clean in pdf_text_clean:
            total_score += 1.0
            found_keywords.append(keyword)
        else:
            key_words = keyword_clean.split()
            matched_words = sum(1 for word in key_words if word in pdf_word_set)
            keyword_score = matched_words / len(key_words) if key_words else 0
            
            if keyword_score > 0.5:
                found_keywords.append(keyword)
            else:
                missing_keywords.append(keyword)
            
            total_score += keyword_score
    
    final_score = (total_score / len(keywords)) * 100
    
    return final_score, found_keywords, missing_keywords

def enhanced_keyword_relevance_score(pdf_text, keywords, title, description):
    with concurrent.futures.ThreadPoolExecutor() as executor:
        keyword_future = executor.submit(keyword_relevance_score, pdf_text, keywords)
        semantic_future = executor.submit(calculate_semantic_relevance_stable, pdf_text, title, description, keywords)
        
        keyword_score, found_kw, missing_kw = keyword_future.result()
        semantic_score = semantic_future.result()
    
    if keywords:
        combined_score = (keyword_score * 0.7) + (semantic_score * 0.3)
    else:
        combined_score = semantic_score
    
    return combined_score, found_kw, missing_kw, semantic_score, keyword_score

def calculate_relevance(keywords, submitted_text, task_title="", task_description=""):
    if not submitted_text or len(submitted_text.strip()) < 50:
        return {
            "relevance_score": 0,
            "grammar_errors": 0,
            "bad_words": [],
            "random_text_confidence": 0,
            "missing_keywords": [],
            "semantic_score": 0,
            "keyword_score": 0,
            "analysis_details": "Submitted text is too short or empty"
        }
    
    with concurrent.futures.ThreadPoolExecutor() as executor:
        enhanced_future = executor.submit(enhanced_keyword_relevance_score, submitted_text, keywords, task_title, task_description)
        grammar_future = executor.submit(detect_grammatical_errors, submitted_text)
        random_future = executor.submit(detect_random_text, submitted_text)
        bad_words_future = executor.submit(contains_bad_words, submitted_text)
        
        combined_score, found_kw, missing_kw, semantic_only_score, keyword_only_score = enhanced_future.result()
        grammar_count, grammar_types = grammar_future.result()
        has_random, random_conf = random_future.result()
        has_bad, bad_words_found = bad_words_future.result()
    
    base_score = combined_score
    penalty = 0
    penalty_reasons = []
    
    if has_bad:
        penalty += 40
        penalty_reasons.append("inappropriate language")
    
    if has_random and random_conf > 30:
        penalty += 30
        penalty_reasons.append("random/repetitive content")
    
    if grammar_count > 20:
        penalty += 20
        penalty_reasons.append(f"high grammar errors ({grammar_count})")
    elif grammar_count > 10:
        penalty += 10
        penalty_reasons.append(f"grammar issues ({grammar_count})")
    
    final_score = max(0, base_score - penalty)
    
    analysis_text = f"Keyword coverage: {keyword_only_score:.1f}%, Semantic relevance: {semantic_only_score:.1f}%"
    if penalty > 0:
        analysis_text += f", Penalties applied: {penalty}% ({', '.join(penalty_reasons)})"
    
    return {
        "relevance_score": int(final_score),
        "grammar_errors": grammar_count,
        "bad_words": bad_words_found,
        "random_text_confidence": random_conf,
        "missing_keywords": missing_kw[:10],
        "semantic_score": int(semantic_only_score),
        "keyword_score": int(keyword_only_score),
        "analysis_details": analysis_text
    }

@app.route('/analyze', methods=['POST'])
def analyze_relevance():
    start_time = time.time()
    
    data = request.json
    task_keywords = data.get('task_keywords', '')
    pdf_path = data.get('pdf_path', '')
    task_title = data.get('task_title', '')
    task_description = data.get('task_description', '')
    
    print(f"DEBUG: Analyzing PDF: {pdf_path}", file=sys.stderr)
    
    if not os.path.exists(pdf_path):
        return jsonify({
            "relevance_score": 0,
            "grammar_errors": 0,
            "bad_words": [],
            "random_text_confidence": 0,
            "missing_keywords": [],
            "semantic_score": 0,
            "keyword_score": 0,
            "analysis_details": f"PDF file not found: {pdf_path}"
        })
    
    submitted_text = extract_text_from_pdf(pdf_path)
    
    if not submitted_text:
        return jsonify({
            "relevance_score": 0,
            "grammar_errors": 0,
            "bad_words": [],
            "random_text_confidence": 0,
            "missing_keywords": [],
            "semantic_score": 0,
            "keyword_score": 0,
            "analysis_details": "Could not extract text from PDF"
        })
    
    keywords = [k.strip() for k in task_keywords.split(',') if k.strip()]
    
    result = calculate_relevance(keywords, submitted_text, task_title, task_description)
    
    processing_time = time.time() - start_time
    print(f"DEBUG: STABLE analysis completed in {processing_time:.2f} seconds", file=sys.stderr)
    
    return jsonify(result)

@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({
        "status": "healthy", 
        "service": "stable_relevance_analysis",
        "bert_loaded": BERT_LOADED,
        "bert_warmed_up": True
    })

if __name__ == "__main__":
    print("DEBUG: Starting STABLE relevance server on port 5001...", file=sys.stderr)
    app.run(host='localhost', port=5001, debug=False, threaded=True)