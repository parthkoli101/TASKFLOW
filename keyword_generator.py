import nltk
from nltk.corpus import stopwords, wordnet
from rake_nltk import Rake
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
nltk.download('punkt', quiet=True)
nltk.download('stopwords', quiet=True)
nltk.download('wordnet', quiet=True)
STOP_WORDS = set(stopwords.words('english'))

def generate_keywords_fast(title, description, top_n=7):
    text = title + ". " + description
    rake = Rake(stopwords=STOP_WORDS, min_length=1, max_length=3)
    rake.extract_keywords_from_text(text)
    candidates = rake.get_ranked_phrases()
    expanded_candidates = set()
    for phrase in candidates:
        expanded_candidates.add(phrase)
        for word in phrase.split():
            synsets = wordnet.synsets(word)
            if synsets:
                lemma = synsets[0].lemmas()[0].name().replace('_', ' ')
                if lemma.lower() not in STOP_WORDS:
                    expanded_candidates.add(lemma)
    candidate_list = list(expanded_candidates)
    vectorizer = TfidfVectorizer(stop_words='english')
    tfidf_matrix = vectorizer.fit_transform([text] + candidate_list)
    text_vec = tfidf_matrix[0]
    candidate_vecs = tfidf_matrix[1:]
    similarities = cosine_similarity(text_vec, candidate_vecs)[0]
    ranked_indices = similarities.argsort()[::-1]
    top_keywords = [candidate_list[i] for i in ranked_indices[:top_n]]
    return ', '.join(top_keywords)

if __name__ == "__main__":
    import sys
    if len(sys.argv) == 3:
        title = sys.argv[1]
        description = sys.argv[2]
        keywords = generate_keywords_fast(title, description, top_n=7)
        print(keywords)
