<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
"# intecbulleting"

# -_- coding: utf-8 -_-

"""
EXTRACTION CARNETS INTEC - PRIMAIRE (DETECTION AUTOMATIQUE & DYNAMIQUE)
========================================================================
Lit chaque carnet .docx et detecte automatiquement :

- les matieres presentes (FRANCAIS, MATHS, ARABE/ANGLAIS, SCIENCES, EPS,
  TECHNOLOGIE, DICTEE, ...)
- les competences (CB1, CB2, ...) propres a chaque matiere
- le niveau de maitrise A / EVA / NA pour chaque note
- les totaux : Total /140, Moyenne /10, Moyenne de la classe, Discipline
- les observations (appreciations) generales de chaque periode

Genere un fichier Excel par classe avec Periode 1 et Periode 2 cote a cote.

NOUVEAUTES (version dynamique) :

- Matricule INTEC genere aleatoirement si absent du carnet (reproductible
  par eleve grace a un hash deterministe sur (classe, nom)).
- Annee academique, niveau, classe, section detectes ou injectes
  automatiquement et exposes dans l'Excel.
- Schema (matieres / competences) entierement dependant des donnees
  rencontrees : aucune matiere ou competence n'est imposee.
- CP B est la seule classe configuree avec Trim 1 ET Trim 2. Les autres
  classes n'ont que le Trim 2 (conformement a la realite des carnets).

Usage :

1. Adapter la section CONFIGURATION
2. python extract_carnets.py
   """

from **future** import annotations

import hashlib
import random
import re
import shutil
import sys
import tempfile
import traceback
import zipfile
from collections import OrderedDict
from pathlib import Path

from docx import Document
from openpyxl import Workbook
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter

# ============================================================================

# CONFIGURATION

# ============================================================================

ACADEMIC_YEAR = "2025-2026"
NIVEAU = "Primaire"
SECTION = "Francophone" # section pedagogique par defaut (modifiable par classe)

# IMPORTANT : utilisez bien les chemins EXACTS tels qu'ils apparaissent

# dans l'Explorateur Windows. Les caracteres accentues (e, a, ...) DOIVENT

# etre conserves : Python 3 + r"..." gere parfaitement les chemins UTF-8.

BASE_TRIM1 = r"C:\Users\Thinkpad\Documents\2027\New folder\Carnets-Intec 2025-2026\Carnet primaire-2025- 2026\Carnets Intec primaire- Trim1-2025-2026"

# Trim 2 : on pointe vers le dossier RACINE, pas le sous-dossier interne.

# Le script trouvera ensuite le sous-dossier de chaque classe automatiquement

# (CPA, CPB, CE1 A, ...) car find_class_folder() fait une recherche tolerante.

BASE_TRIM2 = r"C:\Users\Thinkpad\Documents\2027\New folder\Carnets-Intec 2025-2026\Carnet primaire-2025- 2026\Carnets Intec primaire- Trim2-2025-2026"

OUTPUT_DIR = r"C:\Users\Thinkpad\Documents\2027\New folder\Carnets-Intec 2025-2026_extracted"

# IMPORTANT :

# - Seule CP B possede des carnets Trim 1 ET Trim 2.

# - Toutes les autres classes (CPA, CE1A/B, CE2A/B, CM1, CM2) n'ont QUE le Trim 2.

#

# Le champ "section" peut etre adapte par classe si besoin.

CLASSES = [
{"code": "CPA", "niveau": "CP", "section": SECTION,
"trim1_dir": None,
"trim2_dir": "CARNET CPA -Trim 2-2025-2026"},

    {"code": "CPB",  "niveau": "CP",  "section": SECTION,
     "trim1_dir": "CARNET CPB -Trim1-2025-2026",
     "trim2_dir": "carnets de CP B Trim 2 - 2025-2026"},

    {"code": "CE1A", "niveau": "CE1", "section": SECTION,
     "trim1_dir": None,
     "trim2_dir": "CARNETS - CE1 A -2eme Trim-2025-2026"},

    {"code": "CE1B", "niveau": "CE1", "section": SECTION,
     "trim1_dir": None,
     "trim2_dir": "carnet CE1 B - 2eme TRIM - 2025-2026"},

    {"code": "CE2A", "niveau": "CE2", "section": SECTION,
     "trim1_dir": None,
     "trim2_dir": "CARNET CE2 A- Trim 2 - 2025-2026"},

    {"code": "CE2B", "niveau": "CE2", "section": SECTION,
     "trim1_dir": None,
     "trim2_dir": "carnet CE2B-Trim2-2025-2026"},

    {"code": "CM1",  "niveau": "CM1", "section": SECTION,
     "trim1_dir": None,
     "trim2_dir": "carnets cm1 2eme TRIM 2025-2026"},

    {"code": "CM2",  "niveau": "CM2", "section": SECTION,
     "trim1_dir": None,
     "trim2_dir": "carnet trim2 - CM2 - 2025-2026"},

]

LEVELS = ["A", "EVA", "NA"]

# Prefixe utilise pour les matricules generes (lorsque le carnet n'en

# fournit pas). Format final : INTEC-AAAA-NNNN ou AAAA est l'annee

# academique (4 chiffres) et NNNN un nombre a 4 chiffres deterministe.

MATRICULE_PREFIX = "INTEC"

# ============================================================================

# DICTIONNAIRE DE MATIERES (entierement dynamique : on ajoute toujours,

# jamais on impose)

# ============================================================================

SUBJECT_ALIASES = OrderedDict([
("FRANCAIS", [
"FRANCAIS", "FRANCAIS", "COMMUNICATION ORALE",
"LECTURE", "EXPRESSION ECRITE",
"PRODUCTION ECRITE",
"GRAMMAIRE", "CONJUGAISON", "VOCABULAIRE",
]),
("MATHEMATIQUES", [
"MATHEMATIQUES", "MATHS",
"NUMERATION", "GEOMETRIE",
]),
("ARABE / ANGLAIS", [
"ARABE/ANGLAIS", "ARABE / ANGLAIS", "ARABE-ANGLAIS",
]),
("ARABE", ["ARABE"]),
("ANGLAIS", ["ANGLAIS", "ENGLISH"]),
("SCIENCES", [
"SCIENCES", "EVEIL SCIENTIFIQUE",
"MONDE DU VIVANT", "DECOUVERTE DU MONDE",
"QUESTIONNER LE MONDE",
]),
("EDUCATION PHYSIQUE ET SPORTIVE", [
"EDUCATION PHYSIQUE", "E.P.S", " EPS ", "SPORT", "SPORTIVE",
]),
("HISTOIRE-GEOGRAPHIE", [
"HISTOIRE", "GEOGRAPHIE", "HIST-GEO",
]),
("EDUCATION CIVIQUE", [
"EDUCATION CIVIQUE", "INSTRUCTION CIVIQUE", "EMC", "MORALE",
]),
("TECHNOLOGIE", [
"TECHNOLOGIE", "INFORMATIQUE", "TICE", "ROBOTIQUE",
]),
("ARTS", [
"ARTS PLASTIQUES", "MUSIQUE", "ARTS VISUELS", "EDUCATION MUSICALE",
]),
("DICTEE", ["DICTEE", "ORTHOGRAPHE"]),
])

COMPETENCE_KEYWORDS = [
r"\bCB\s*\d+\b",
r"\bC\s*\d+\b",
r"\bCOMP[EE]TENCE\s\*\d+\b",
r"\bECRITURE\b",
r"\bLECTURE\b",
r"\bORAL\b",
r"\bNOTE\b",
r"\bMA[IT]TRISER\b",
r"\bIDENTIFIER\b",
r"\bPRODUIRE\b",
r"\bNOMMER\b",
r"\bLANCER\b",
r"\bREPRODUIRE\b",
r"\bRESOUDRE\b",
r"\bSE\s+SITUER\b",
r"\bTRIER\b",
r"\bALLUMER\b",
r"\bINTRODUIRE\b",
r"\bCREER\b",
r"\bAPPREHENDER\b",
r"\bDISCIPLINE\b",
]

OBSERVATION_KEYWORDS = [
"OBSERVATION", "APPRECIATION", "COMMENTAIRE", "REMARQUE", "AVIS",
]

IGNORE_LABEL_KEYWORDS = [
r"\bTOTAL\b", r"\bMOYENNE\b", r"\bRANG\b", r"\bBAREME\b",
r"\bDEGRE\b", r"\bSEUIL\b", r"\bPERIODE\b",
]

# ============================================================================

# OUVERTURE SECURISEE DES DOCX

# ============================================================================

def _repair_docx(src_path):
tmp_dir = Path(tempfile.mkdtemp(prefix="docx_repair_"))
try:
with zipfile.ZipFile(src_path, "r") as zin:
members = set(zin.namelist())
zin.extractall(tmp_dir)

        for rels_file in tmp_dir.rglob("*.rels"):
            try:
                content = rels_file.read_text(encoding="utf-8", errors="ignore")
            except Exception:
                continue

            new_content = content
            for match in re.finditer(r"<Relationship\b[^>]*?/>", content):
                rel_tag = match.group(0)
                target_match = re.search(r'Target="([^"]+)"', rel_tag)
                if not target_match:
                    continue
                target = target_match.group(1)
                if target.startswith(("http://", "https://", "mailto:", "file:")):
                    continue
                if 'TargetMode="External"' in rel_tag:
                    continue

                target_clean = target.lstrip("/")
                if target_clean.upper() == "NULL" or target_clean == "":
                    new_content = new_content.replace(rel_tag, "")
                    continue

                candidates = [target_clean]
                rels_parent = rels_file.parent.parent
                try:
                    rel_to_root = rels_parent.relative_to(tmp_dir).as_posix()
                    if rel_to_root and rel_to_root != ".":
                        candidates.append(f"{rel_to_root}/{target_clean}")
                except ValueError:
                    pass
                while target_clean.startswith("../"):
                    target_clean = target_clean[3:]
                    candidates.append(target_clean)

                if not any(c in members for c in candidates):
                    new_content = new_content.replace(rel_tag, "")

            if new_content != content:
                rels_file.write_text(new_content, encoding="utf-8")

        tmp_zip = tempfile.NamedTemporaryFile(
            suffix=".docx", delete=False, prefix="docx_fixed_"
        )
        tmp_zip.close()
        with zipfile.ZipFile(tmp_zip.name, "w", zipfile.ZIP_DEFLATED) as zout:
            for f in tmp_dir.rglob("*"):
                if f.is_file():
                    arcname = f.relative_to(tmp_dir).as_posix()
                    zout.write(f, arcname)
        return tmp_zip.name
    finally:
        shutil.rmtree(tmp_dir, ignore_errors=True)

def safe_open_docx(path):
try:
return Document(str(path)), None
except Exception as e:
msg = str(e).lower()
if "no item named" in msg or "package" in msg or "rels" in msg:
try:
fixed = \_repair_docx(path)
return Document(fixed), fixed
except Exception:
raise e
raise

# ============================================================================

# OUTILS DE BASE

# ============================================================================

def normalize(text):
if not text:
return ""
return re.sub(r"\s+", " ", str(text).upper()).strip()

def clean_note(text):
"""
Convertit une cellule en note numerique sur /20.
IMPORTANT : 0, 1, 2... sont des notes valides - ne JAMAIS les filtrer !
"""
if text is None:
return None
t = str(text).strip().replace(",", ".").replace("\xa0", " ")
if not t:
return None
t = re.sub(r"/\s\*\d+(\.\d+)?", "", t)
match = re.search(r"(\d+\.\d+|\d+)", t)
if not match:
return None
try:
val = float(match.group(1))
except ValueError:
return None
if 0 <= val <= 20:
return val
return None

def clean_total_value(text):
"""Pour les totaux/moyennes : accepte aussi des valeurs jusqu'a 200."""
if text is None:
return None
t = str(text).strip().replace(",", ".").replace("\xa0", " ")
if not t:
return None
t = re.sub(r"/\s\*\d+(\.\d+)?", "", t)
match = re.search(r"(\d+\.\d+|\d+)", t)
if not match:
return None
try:
return float(match.group(1))
except ValueError:
return None

def canonical_subject(text):
t = normalize(text)
if not t:
return None
for canonical, aliases in SUBJECT_ALIASES.items():
for alias in aliases:
if normalize(alias) in t:
return canonical
return None

def is_competence_label(text):
t = normalize(text)
if not t or len(t) > 400:
return False
if any(re.search(k, t) for k in IGNORE_LABEL_KEYWORDS):
return False
return any(re.search(k, t) for k in COMPETENCE_KEYWORDS)

def normalize_competence(text):
"""Convertit 'CB 2/Lecture : ...' -> 'CB2 / Lecture'."""
t = normalize(text)
m = re.search(
r"\bCB\s*(\d+)\s*[/]\s*([A-Z][A-Za-z\-\s]*?)(?=\s\*[:.])",
t,
)
if m:
num = m.group(1)
sub = m.group(2).strip()
words = sub.split()
if words:
stop_verbs = {
"IDENTIFIER", "PRODUIRE", "RESOUDRE", "SE",
"TRIER", "LANCER", "REPRODUIRE", "NOMMER", "ALLUMER",
"INTRODUIRE", "MAITRISER", "CREER",
"ECRIRE", "APPREHENDER",
}
kept = []
for w in words[:3]:
if w.upper() in stop_verbs:
break
kept.append(w)
sub = " ".join(kept).rstrip(":").strip()
return f"CB{num}" + (f" / {sub.title()}" if sub else "")

    m = re.search(r"\bCB\s*(\d+)\b", t)
    if m:
        num = m.group(1)
        rest = t[m.end():]
        m2 = re.search(
            r"[/:]?\s*(ARABE|ANGLAIS|INFORMATIQUE|ROBOTIQUE|LECTURE|"
            r"ECRITURE|LANGAGE|NUMERATION|"
            r"GEOMETRIE|MESURE|PRODUCTION\s+ECRITE)",
            rest,
        )
        if m2:
            return f"CB{num} / {m2.group(1).title()}"
        return f"CB{num}"

    if re.search(r"\bECRITURE\b", t):
        return "ECRITURE"
    if re.search(r"\bLECTURE\b", t):
        return "LECTURE"
    if re.search(r"\bMA[IT]TRISER.*ORTHOGRAPHE\b|\bORTHOGRAPHE\b", t):
        return "DICTEE"
    if re.search(r"\bORAL\b", t):
        return "ORAL"
    if re.search(r"\bDISCIPLINE\b", t):
        return "DISCIPLINE"
    if re.search(r"\bNOTE\b", t):
        return "NOTE"
    return t[:30]

def is_observation_label(text):
t = normalize(text)
return any(k in t for k in OBSERVATION_KEYWORDS)

# ============================================================================

# LECTURE COMPLETE DU XML (texte dans text-boxes inclus)

# ============================================================================

W_NS = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"

def \_all_text_in_body(doc):
parts = []
for elem in doc.element.body.iter():
if elem.tag == f"{{{W_NS}}}t" and elem.text:
parts.append(elem.text)
return "\n".join(parts)

# ============================================================================

# METADONNEES ELEVE

# ============================================================================

def extract_matricule(doc):
"""Tente d'extraire un matricule INTEC du document. Retourne None sinon."""
full = \_all_text_in_body(doc).upper()
m = re.search(r"INTEC[-\s]?\d+(?:[-\s]?\d+)?", full)
if m:
raw = m.group(0)
return re.sub(r"\s+", "-", raw)
return None

def generate_matricule(class_code: str, student_name: str,
academic_year: str = ACADEMIC_YEAR,
prefix: str = MATRICULE_PREFIX) -> str:
"""
Genere un matricule unique et reproductible pour un eleve.

    Format : <PREFIX>-<YYYY>-<NNNN>
       - YYYY = premiere annee academique (ex: 2025 pour 2025-2026)
       - NNNN = nombre a 4 chiffres deterministe base sur (classe, nom)

    On utilise un hash determinist (et non random.random()) pour que le meme
    eleve obtienne TOUJOURS le meme matricule entre deux executions du script.
    """
    year_part = academic_year.split("-")[0] if academic_year else "0000"
    seed_str = f"{prefix}|{academic_year}|{class_code}|{normalize(student_name)}"
    h = hashlib.sha256(seed_str.encode("utf-8")).hexdigest()
    # 4 derniers chiffres entre 0001 et 9999
    num = (int(h[:8], 16) % 9999) + 1
    return f"{prefix}-{year_part}-{num:04d}"

FRENCH_MONTHS = {
"janvier": 1, "fevrier": 2, "fevrier": 2, "mars": 3, "avril": 4,
"mai": 5, "juin": 6, "juillet": 7, "aout": 8, "aout": 8,
"septembre": 9, "octobre": 10, "novembre": 11, "decembre": 12,
"janv": 1, "fev": 2, "mar": 3, "avr": 4, "jui": 6, "juil": 7,
"sep": 9, "oct": 10, "nov": 11, "dec": 12,
}

def normalize_birthdate(raw):
"""
Normalise une date de naissance en format d/m/yyyy.

    Accepte en entree :
      - "21/05/2018", "21-5-18", "21.05.2018"
      - "21 mai 2018", "21 mai 18"
      - "2018-05-21" (ISO)
      - "21052018", "210518" (compact)

    Retourne une chaine d/m/yyyy. Annee a 2 chiffres : on prefixe 19 si
    >= 30 (ex 95 -> 1995) sinon 20 (ex 18 -> 2018).
    """
    if not raw:
        return ""
    s = str(raw).strip()
    if not s:
        return ""

    # Retirer les caracteres parasites
    s = s.replace("\xa0", " ")
    s = re.sub(r"\s+", " ", s).strip()
    s = s.strip(" :-\t.,")

    def _expand_year(y):
        y = int(y)
        if y < 100:
            return 2000 + y if y < 30 else 1900 + y
        return y

    # Format 1 : ISO yyyy-mm-dd
    m = re.fullmatch(r"(\d{4})\s*[\-/.]\s*(\d{1,2})\s*[\-/.]\s*(\d{1,2})", s)
    if m:
        y, mo, d = m.group(1), m.group(2), m.group(3)
        return f"{int(d)}/{int(mo)}/{int(y):04d}"

    # Format 2 : d/m/y (ou - ou .)
    m = re.fullmatch(
        r"(\d{1,2})\s*[\s\-/.]\s*(\d{1,2})\s*[\s\-/.]\s*(\d{2,4})", s)
    if m:
        d, mo, y = m.group(1), m.group(2), m.group(3)
        return f"{int(d)}/{int(mo)}/{_expand_year(y):04d}"

    # Format 3 : d <mois en lettres> y
    m = re.fullmatch(r"(\d{1,2})\s+([A-Za-z]+)\s+(\d{2,4})", s)
    if m:
        d, mname, y = m.group(1), m.group(2).lower(), m.group(3)
        # Retirer les accents (sale mais efficace)
        mname_clean = (mname.replace("e", "e").replace("e", "e")
                       .replace("u", "u").replace("a", "a"))
        # En realite on a deja retire les accents en amont par normalize() ;
        # ici on cherche la cle par "startswith" dans FRENCH_MONTHS.
        mo = None
        for k, v in FRENCH_MONTHS.items():
            if mname_clean.startswith(k) or k.startswith(mname_clean):
                mo = v
                break
        if mo:
            return f"{int(d)}/{mo}/{_expand_year(y):04d}"

    # Format 4 : compact ddmmyyyy ou ddmmyy
    m = re.fullmatch(r"(\d{2})(\d{2})(\d{2,4})", s)
    if m:
        d, mo, y = m.group(1), m.group(2), m.group(3)
        return f"{int(d)}/{int(mo)}/{_expand_year(y):04d}"

    # Aucun format reconnu -> retourner tel quel
    return s

def extract_birthdate(doc):
"""Extrait la date de naissance et la normalise en d/m/yyyy."""
full_text = \_all_text_in_body(doc)
patterns = [
r"(?:N[EE]E?\s*\(?E?\)?\s+LE|DATE\s+DE\s+NAISSANCE)\s*[:\-]?\s*"
r"([0-9]{1,2}[\s/\-\.][0-9]{1,2}[\s/\-\.][0-9]{2,4})",
r"(?:N[EE]E?\s*\(?E?\)?\s+LE|DATE\s+DE\s+NAISSANCE)\s*[:\-]?\s*"
r"([0-9]{1,2}\s+[A-Za-z]+\s+[0-9]{2,4})", # Format ISO si jamais
r"(?:N[EE]E?\s*\(?E?\)?\s+LE|DATE\s+DE\s+NAISSANCE)\s*[:\-]?\s\*"
r"([0-9]{4}[\-/][0-9]{1,2}[\-/][0-9]{1,2})",
]
for pat in patterns:
m = re.search(pat, full_text, re.IGNORECASE)
if m:
return normalize_birthdate(m.group(1).strip())
return ""

def \_split_full_name(full):
"""
Heuristique simple pour separer NOM (familial) et PRENOM(S).

    Conventions courantes dans les ecoles francophones :
      - Si le nom est tout en MAJUSCULES suivi de mots Capitalises :
          "MOHAMED Ahmed Said" -> nom="MOHAMED", prenom="Ahmed Said"
      - Sinon, on suppose le format "Nom Prenom1 Prenom2" :
          "Mohamed Ahmed Said" -> nom="Mohamed", prenom="Ahmed Said"
      - Si un seul mot, c'est le nom.

    Retourne (nom_de_famille, prenoms).
    """
    if not full:
        return ("", "")
    parts = [p for p in re.split(r"\s+", str(full).strip()) if p]
    if not parts:
        return ("", "")
    if len(parts) == 1:
        return (parts[0].title(), "")

    # Cas 1 : un ou plusieurs mots tout en majuscules au debut
    upper_run = []
    rest = []
    for w in parts:
        if w.isupper() and len(w) > 1:
            upper_run.append(w)
        else:
            rest.append(w)
            # Une fois qu'un mot non-majuscule apparait, le reste est prenom
            break
    # Recuperer la suite
    idx = len(upper_run) + len(rest)
    rest = rest + parts[idx:]

    if upper_run:
        nom = " ".join(upper_run).title()
        prenom = " ".join(rest).title()
        return (nom, prenom)

    # Cas 2 : pas de bloc majuscule -> on prend le 1er mot comme nom
    nom = parts[0].title()
    prenom = " ".join(parts[1:]).title()
    return (nom, prenom)

def extract_student_name(doc, file_path):
"""
Retourne (full_name, nom, prenom) - tous les trois en str.
full_name reste compatible avec l'ancienne API ; nom/prenom sont les
composantes separees.
"""
candidates = []
head_text = \_all_text_in_body(doc)

    patterns = [
        r"(?:NOM\s+DE\s+L['\u2019]?\s*[EE]?L[EE]VE|NOM\s+DE\s+L['\u2019]?\s*ELEVE)"
        r"\s*[:\-]\s*([A-Z][A-Za-z\-' ]{2,80})",
        r"(?:NOM\s+ET\s+PR[EE]NOMS?|NOMS?\s+ET\s+PR[EE]NOMS?|"
        r"PR[EE]NOMS?\s+ET\s+NOMS?)\s*[:\-]\s*([A-Z][A-Za-z\-' ]{2,80})",
        r"(?:^|\s)(?:NOM|[EE]L[EE]VE|ELEVE|PR[EE]NOMS?)\s*[:\-]\s*"
        r"([A-Z][A-Za-z\-' ]{2,80})",
    ]
    for pat in patterns:
        for m in re.finditer(pat, head_text, re.IGNORECASE):
            cand = m.group(1).strip()
            cand = re.split(
                r"\b(?:DATE|MATRICULE|N[EE]\(?E?\)?|CLASSE|"
                r"[EE]COLE|ECOLE|ANN[EE]E|NOM)\b",
                cand, flags=re.IGNORECASE,
            )[0]
            cand = cand.strip(" :-\t\xa0")
            if cand and 2 < len(cand) < 80:
                candidates.append(cand)

    full_name = ""
    if candidates:
        candidates.sort(key=lambda x: -len(x))
        full_name = candidates[0]
    else:
        # Fallback : extraire du nom de fichier
        stem = Path(file_path).stem
        stem = re.sub(
            r"^(?:CARNET[_\s]*(?:CM\d|CE\d|CP)?[_\s]*)?(?:D['\u2019]\s*|DE\s+)?",
            "", stem, flags=re.IGNORECASE,
        )
        stem = re.sub(r"\s*[\(\-_]\s*(?:Copie|copy|\d+)\s*\)?\s*$", "", stem,
                      flags=re.IGNORECASE)
        stem = re.sub(r"[_]+", " ", stem).strip()
        full_name = stem if stem else "Inconnu"

    nom, prenom = _split_full_name(full_name)
    # Reconstituer un full_name propre : on garde la casse d'origine si
    # elle a une majuscule de famille, sinon on titlecase l'ensemble
    if nom and prenom:
        full_clean = f"{nom} {prenom}"
    elif nom:
        full_clean = nom
    else:
        full_clean = full_name.title()
    return full_clean, nom, prenom

# ============================================================================

# DETECTION DE SECTION

# ============================================================================

SECTION_HEADER_RE = re.compile(
r"COMP[EE]TENCE\s+DE\s+BASE\s*:\s*"
r"([A-Z][A-Z\s\-/]+?)\s*[:/]?\s*/?\s*\d|"
r"COMP[EE]TENCE\s+DE\s+BASE\s*:\s*([A-Z][A-Z\s\-/]+?)\s*$",
re.IGNORECASE,
)
STANDALONE_DICTEE_RE = re.compile(
r"^\s*[IVX]+\s*[\-]\s\*(DICT[EE]E)\b", re.IGNORECASE,
)
DIM_PERSONNELLE_RE = re.compile(r"DIMENSION\s+PERSONNELLE", re.IGNORECASE)

def \_build_table_position_map(doc):
positions = {}
idx = 0
for child in doc.element.body.iterchildren():
if child.tag.endswith("}tbl"):
positions[idx] = child
idx += 1
return positions

def detect_section_before_table(doc, table_index, table_positions):
target = table_positions.get(table_index)
if target is None:
return None, False

    last_subject = None
    last_is_dim = False
    for child in doc.element.body.iterchildren():
        if child is target:
            break
        if not child.tag.endswith("}p"):
            continue
        text = "".join(t.text or "" for t in child.iter() if t.tag.endswith("}t"))
        if not text.strip():
            continue
        m = SECTION_HEADER_RE.search(text)
        if m:
            section = (m.group(1) or m.group(2) or "").strip()
            sub = canonical_subject(section)
            if sub:
                last_subject = sub
                last_is_dim = False
            continue
        m2 = STANDALONE_DICTEE_RE.search(text)
        if m2:
            last_subject = "DICTEE"
            last_is_dim = False
            continue
        if DIM_PERSONNELLE_RE.search(text):
            last_is_dim = True
            last_subject = None
            continue
    return last_subject, last_is_dim

# ============================================================================

# EXTRACTION DES TABLEAUX

# ============================================================================

def parse_competence_row(label, note_cells):
period_data = OrderedDict()
n = len(note_cells)

    if n >= 6:
        n_periods = min(3, n // 3)
        for p_idx in range(n_periods):
            chunk = note_cells[p_idx * 3:p_idx * 3 + 3]
            level_data = {"A": None, "EVA": None, "NA": None,
                          "value": None, "level": None}
            for lvl_idx, lvl in enumerate(LEVELS):
                if lvl_idx < len(chunk):
                    val = clean_note(chunk[lvl_idx])
                    level_data[lvl] = val
                    if val is not None and level_data["value"] is None:
                        level_data["value"] = val
                        level_data["level"] = lvl
            period_data[f"P{p_idx + 1}"] = level_data
    else:
        for p_idx, raw in enumerate(note_cells):
            val = clean_note(raw)
            period_data[f"P{p_idx + 1}"] = {
                "A": None, "EVA": None, "NA": None,
                "value": val, "level": None,
            }
    return period_data

def extract_table_notes(table):
competences = OrderedDict()
for row in table.rows:
cells = [c.text.strip() for c in row.cells]
if len(cells) < 2:
continue
label = cells[0]
if not is_competence_label(label):
continue
comp_name = normalize_competence(label)
if comp_name in competences:
continue
competences[comp_name] = parse_competence_row(label, cells[1:])
return competences

def extract_dimension_personnelle(table):
result = {"P1": "", "P2": "", "P3": ""}
for row in table.rows:
cells = [c.text.strip() for c in row.cells]
if len(cells) < 2:
continue
for i, key in enumerate(["P1", "P2", "P3"]):
if i + 1 < len(cells):
val = cells[i + 1].strip()
if val:
result[key] = val
break
return result

def extract_totaux(table):
totaux = OrderedDict()
for row in table.rows:
cells = [c.text.strip() for c in row.cells]
if len(cells) < 2:
continue
label = cells[0]
label_norm = normalize(label)
if not (re.search(r"\bTOTAL\b", label_norm)
or re.search(r"\bMOYENNE\b", label_norm)):
continue
period_vals = {"P1": None, "P2": None, "P3": None}
for i, key in enumerate(["P1", "P2", "P3"]):
if i + 1 < len(cells):
period_vals[key] = clean_total_value(cells[i + 1])
totaux[label.strip()] = period_vals
return totaux

def extract_appreciations(table):
appreciations = {"P1": "", "P2": "", "P3": ""}
for row in table.rows:
cells = [c.text.strip() for c in row.cells]
if len(cells) < 2:
continue
label = normalize(cells[0])
period_key = None
if re.search(r"\b1\s*[EE]?RE\b", label) or re.search(r"^1[\s/]", label):
period_key = "P1"
elif re.search(r"\b2\s*[EE]?ME\b", label) or re.search(r"^2[\s/]", label):
period_key = "P2"
elif re.search(r"\b3\s*[EE]?ME\b", label) or re.search(r"^3[\s/]", label):
period_key = "P3"
if period_key:
obs = cells[1].strip()
obs = re.sub(r"L['\u2019]?\s*Enseignant\(?e\)?\s*\.?\s*$", "", obs,
flags=re.IGNORECASE)
obs = re.sub(r"[/\\]+", " ", obs)
obs = re.sub(r"\s+", " ", obs).strip()
appreciations[period_key] = obs
return appreciations

def is_dimension_personnelle_table(table):
if len(table.rows) > 2:
return False
for row in table.rows[:1]:
cells = [c.text.strip() for c in row.cells]
if cells:
t = normalize(cells[0])
if ("DISCIPLINE" in t or "ASSIDUITE" in t
or "PONCTUALITE" in t):
return True
return False

def is_totaux_table(table):
for row in table.rows[:5]:
cells = [c.text.strip() for c in row.cells]
if cells:
t = normalize(cells[0])
if (re.search(r"\bTOTAL\s+SUR\b", t)
or re.search(r"\bMOYENNE\s+SUR\b", t)
or re.search(r"\bMOYENNE\s+DE\s+LA\s+CLASSE\b", t)):
return True
return False

def is_appreciations_table(table):
if not table.rows:
return False
cells0 = [c.text.strip() for c in table.rows[0].cells]
if len(cells0) < 2:
return False
t0 = normalize(cells0[0])
t1 = normalize(cells0[1]) if len(cells0) > 1 else ""
return (("PERIODE" in t0)
and ("OBSERVATION" in t1 or "APPRECIATION" in t1))

# ============================================================================

# EXTRACTION COMPLETE D'UN CARNET

# ============================================================================

def \_extract_from_doc(doc, doc_path, class_code: str):
full_name, nom, prenom = extract_student_name(doc, doc_path)
matricule = extract_matricule(doc)
if not matricule or matricule.upper().startswith("INTEC-N/A"): # Generation deterministe (memes valeurs entre executions)
matricule = generate_matricule(class_code, full_name)
birthdate = extract_birthdate(doc)
table_positions = \_build_table_position_map(doc)

    data = OrderedDict()
    totaux = OrderedDict()
    discipline = {"P1": "", "P2": "", "P3": ""}
    appreciations = {"P1": "", "P2": "", "P3": ""}

    current_subject = None
    in_dim_personnelle = False

    for t_idx, table in enumerate(doc.tables):
        section_subject, is_dim = detect_section_before_table(
            doc, t_idx, table_positions
        )
        if section_subject:
            current_subject = section_subject
            in_dim_personnelle = False
        elif is_dim:
            in_dim_personnelle = True

        if is_appreciations_table(table):
            apps = extract_appreciations(table)
            for k, v in apps.items():
                if v and not appreciations[k]:
                    appreciations[k] = v
            continue

        if is_totaux_table(table):
            totaux.update(extract_totaux(table))
            continue

        if in_dim_personnelle and is_dimension_personnelle_table(table):
            disc = extract_dimension_personnelle(table)
            for k, v in disc.items():
                if v and not discipline[k]:
                    discipline[k] = v
            continue

        if not current_subject:
            continue
        comps = extract_table_notes(table)
        if comps:
            if current_subject not in data:
                data[current_subject] = OrderedDict()
            for k, v in comps.items():
                if k in data[current_subject]:
                    for pk, pv in v.items():
                        existing = data[current_subject][k].get(pk)
                        if not existing or existing.get("value") is None:
                            data[current_subject][k][pk] = pv
                else:
                    data[current_subject][k] = v

    return {
        "matricule": matricule,
        "nom": full_name,         # nom complet (compat. ascendante)
        "nom_famille": nom,       # nom de famille seul
        "prenom": prenom,         # prenom(s) seul(s)
        "naissance": birthdate,
        "data": data,
        "totaux": totaux,
        "discipline": discipline,
        "appreciations": appreciations,
    }

def extract_student_data(doc_path, class_code: str):
doc, tmp_file = safe_open_docx(doc_path)
try:
return \_extract_from_doc(doc, doc_path, class_code)
finally:
if tmp_file:
try:
Path(tmp_file).unlink(missing_ok=True)
except Exception:
pass

# ============================================================================

# SCHEMA DE CLASSE (matieres + competences detectees)

# ============================================================================

def \_safe_save_workbook(wb, target_path):
"""
Sauvegarde le classeur en gerant proprement le cas ou le fichier
cible est deja ouvert dans Excel (Permission denied sous Windows).

    En cas d'echec :
      - tente d'abord <nom>_v2.xlsx, _v3.xlsx, ...
      - en dernier recours : <nom>_<YYYYmmdd_HHMMSS>.xlsx

    Retourne le chemin reellement utilise.
    """
    target_path = Path(target_path)
    target_path.parent.mkdir(parents=True, exist_ok=True)

    # 1er essai : le chemin demande
    try:
        wb.save(target_path)
        return target_path
    except PermissionError:
        print(f"  /!\\ Le fichier '{target_path.name}' est verrouille "
              f"(probablement ouvert dans Excel).")
        print(f"      Tentative de sauvegarde sous un nom alternatif...")

    # 2eme essai : <nom>_v2.xlsx, _v3.xlsx, ... (jusqu'a 10)
    for i in range(2, 11):
        alt = target_path.with_name(
            f"{target_path.stem}_v{i}{target_path.suffix}"
        )
        try:
            wb.save(alt)
            print(f"      -> sauvegarde sous : {alt.name}")
            return alt
        except PermissionError:
            continue

    # 3eme essai : nom horodate (ne devrait jamais echouer)
    from datetime import datetime
    stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    alt = target_path.with_name(
        f"{target_path.stem}_{stamp}{target_path.suffix}"
    )
    wb.save(alt)
    print(f"      -> sauvegarde sous : {alt.name}")
    return alt

def \_sort_competences(comps):
def key(c):
m = re.match(r"^CB(\d+)", c)
if m:
return (0, int(m.group(1)), c)
m = re.match(r"^C(\d+)", c)
if m:
return (1, int(m.group(1)), c)
order = {"ECRITURE": 2, "LECTURE": 3, "ORAL": 4,
"DICTEE": 5, "NOTE": 6}
if c in order:
return (order[c], 0, c)
return (10, 0, c)
return sorted(comps, key=key)

def build_class_schema(students):
subj_comps = OrderedDict()
for std in students:
for subj, comps in std["data"].items():
if subj not in subj_comps:
subj_comps[subj] = OrderedDict()
for comp in comps:
subj_comps[subj][comp] = subj_comps[subj].get(comp, 0) + 1

    nb_students = max(1, len(students))
    threshold = max(1, nb_students // 10) if nb_students >= 10 else 1

    canonical_order = list(SUBJECT_ALIASES.keys())
    schema = OrderedDict()

    def filter_comps(raw_comps):
        comps = [c for c, n in raw_comps.items() if n >= threshold]
        has_specific = any(c not in ("NOTE",) for c in comps)
        if has_specific and "NOTE" in comps:
            comps = [c for c in comps if c != "NOTE"]
        return _sort_competences(comps)

    for subj in canonical_order:
        if subj in subj_comps:
            comps = filter_comps(subj_comps[subj])
            if comps:
                schema[subj] = comps
    for subj in subj_comps:
        if subj not in schema:
            comps = filter_comps(subj_comps[subj])
            if comps:
                schema[subj] = comps
    return schema

def collect_totaux_labels(students):
labels = []
seen = set()
preferred = ["TOTAL SUR 140", "MOYENNE SUR 10", "MOYENNE DE LA CLASSE SUR 10"]
for std in students:
for lbl in std.get("totaux", {}):
n = normalize(lbl)
if n not in seen:
seen.add(n)
labels.append(lbl)

    def sort_key(lbl):
        n = normalize(lbl)
        for i, p in enumerate(preferred):
            if p in n:
                return (i, lbl)
        return (99, lbl)
    return sorted(labels, key=sort_key)

# ============================================================================

# EXPORT SQL

# ============================================================================

def \_sql_escape(s):
"""Echappe une chaine pour SQL standard (doubles les apostrophes)."""
if s is None:
return "NULL"
return "'" + str(s).replace("'", "''") + "'"

def \_sql_num(v):
"""Convertit une valeur numerique pour SQL (NULL si None)."""
if v is None:
return "NULL"
try:
return f"{float(v):g}"
except (ValueError, TypeError):
return "NULL"

def \_sql_date(s):
"""
Convertit une date au format d/m/yyyy (notre format normalise) en
format ISO yyyy-mm-dd accepte par SQL. Si vide ou non parsable,
retourne NULL.
"""
if not s:
return "NULL"
s = str(s).strip()
m = re.fullmatch(r"(\d{1,2})/(\d{1,2})/(\d{4})", s)
if m:
d, mo, y = int(m.group(1)), int(m.group(2)), int(m.group(3))
return f"'{y:04d}-{mo:02d}-{d:02d}'"
return "NULL"

def build_sql_export(class_info, students_p1, students_p2,
schema, totaux_labels,
academic_year=ACADEMIC_YEAR, niveau=NIVEAU):
"""
Genere un script SQL (string) qui : 1) Cree les tables (eleves, matieres, competences, notes, totaux,
appreciations, discipline) si elles n'existent pas. 2) INSERT les donnees de la classe pour cette annee academique.

    Le script utilise INSERT OR REPLACE / ON CONFLICT pour etre idempotent
    (re-execution sans doublons). Compatible SQLite, MySQL et PostgreSQL
    avec quelques adaptations indiquees en commentaires.
    """
    code = class_info["code"]
    class_niveau = class_info.get("niveau", niveau)
    class_section = class_info.get("section", SECTION)

    # Fusion par nom (meme logique que build_workbook)
    by_name = {}
    for std in students_p1 + students_p2:
        key = normalize(std["nom"])
        entry = by_name.setdefault(key, {
            "nom_complet": std["nom"],
            "nom_famille": std.get("nom_famille", ""),
            "prenom": std.get("prenom", ""),
            "matricule": std["matricule"],
            "naissance": std.get("naissance", ""),
            "p1": None, "p2": None,
        })
        if std in students_p1:
            entry["p1"] = std
        if std in students_p2:
            entry["p2"] = std
        if std.get("nom_famille") and not entry["nom_famille"]:
            entry["nom_famille"] = std["nom_famille"]
        if std.get("prenom") and not entry["prenom"]:
            entry["prenom"] = std["prenom"]
        if std.get("naissance") and not entry["naissance"]:
            entry["naissance"] = std["naissance"]

    students = sorted(by_name.values(),
                      key=lambda x: normalize(x["nom_complet"]))

    lines = []
    lines.append("-- ============================================================")
    lines.append(f"-- EXPORT SQL - CARNET INTEC")
    lines.append(f"-- Classe       : {code}")
    lines.append(f"-- Niveau       : {class_niveau}")
    lines.append(f"-- Section      : {class_section}")
    lines.append(f"-- Annee acad.  : {academic_year}")
    lines.append(f"-- Effectif     : {len(students)} eleve(s)")
    lines.append(f"-- Matieres     : {len(schema)}")
    lines.append("-- ")
    lines.append("-- Compatible SQLite / MySQL / PostgreSQL.")
    lines.append("-- Pour MySQL : remplacer 'INSERT OR REPLACE' par")
    lines.append("--   'REPLACE INTO' (et retirer ON CONFLICT pour Postgres).")
    lines.append("-- ============================================================")
    lines.append("")

    # ---------- SCHEMA ----------
    lines.append("-- 1) Schema des tables")
    lines.append("")
    lines.append("""CREATE TABLE IF NOT EXISTS classes (
    code           VARCHAR(20)  PRIMARY KEY,
    niveau         VARCHAR(20)  NOT NULL,
    section        VARCHAR(50),
    annee_acad     VARCHAR(20)  NOT NULL

);""")
lines.append("")
lines.append("""CREATE TABLE IF NOT EXISTS eleves (
matricule VARCHAR(30) PRIMARY KEY,
nom_famille VARCHAR(80),
prenom VARCHAR(120),
nom_complet VARCHAR(200) NOT NULL,
date_naissance DATE,
classe_code VARCHAR(20) NOT NULL,
annee_acad VARCHAR(20) NOT NULL,
FOREIGN KEY (classe_code) REFERENCES classes(code)
);""")
lines.append("")
lines.append("""CREATE TABLE IF NOT EXISTS matieres (
id INTEGER PRIMARY KEY,
libelle VARCHAR(100) NOT NULL UNIQUE
);""")
lines.append("")
lines.append("""CREATE TABLE IF NOT EXISTS competences (
id INTEGER PRIMARY KEY,
matiere_id INTEGER NOT NULL,
libelle VARCHAR(150) NOT NULL,
UNIQUE (matiere_id, libelle),
FOREIGN KEY (matiere_id) REFERENCES matieres(id)
);""")
lines.append("")
lines.append("""CREATE TABLE IF NOT EXISTS notes (
matricule VARCHAR(30) NOT NULL,
competence_id INTEGER NOT NULL,
periode VARCHAR(5) NOT NULL, -- 'P1' ou 'P2'
note DECIMAL(4,2),
niveau_acquis VARCHAR(5), -- 'A', 'EVA', 'NA' ou NULL
annee_acad VARCHAR(20) NOT NULL,
PRIMARY KEY (matricule, competence_id, periode, annee_acad),
FOREIGN KEY (matricule) REFERENCES eleves(matricule),
FOREIGN KEY (competence_id) REFERENCES competences(id)
);""")
lines.append("")
lines.append("""CREATE TABLE IF NOT EXISTS totaux (
matricule VARCHAR(30) NOT NULL,
libelle VARCHAR(100) NOT NULL,
periode VARCHAR(5) NOT NULL,
valeur DECIMAL(6,2),
annee_acad VARCHAR(20) NOT NULL,
PRIMARY KEY (matricule, libelle, periode, annee_acad),
FOREIGN KEY (matricule) REFERENCES eleves(matricule)
);""")
lines.append("")
lines.append("""CREATE TABLE IF NOT EXISTS appreciations (
matricule VARCHAR(30) NOT NULL,
periode VARCHAR(5) NOT NULL,
discipline VARCHAR(100),
observation TEXT,
annee_acad VARCHAR(20) NOT NULL,
PRIMARY KEY (matricule, periode, annee_acad),
FOREIGN KEY (matricule) REFERENCES eleves(matricule)
);""")
lines.append("")
lines.append("")

    # ---------- DONNEES : classe + matieres + competences ----------
    lines.append("-- 2) Insertion classe")
    lines.append(
        f"INSERT OR REPLACE INTO classes (code, niveau, section, annee_acad) "
        f"VALUES ({_sql_escape(code)}, {_sql_escape(class_niveau)}, "
        f"{_sql_escape(class_section)}, {_sql_escape(academic_year)});"
    )
    lines.append("")

    lines.append("-- 3) Insertion matieres et competences")
    matiere_ids = {}
    competence_ids = {}
    next_mat_id = 1
    next_comp_id = 1
    for subj, comps in schema.items():
        mat_id = next_mat_id
        matiere_ids[subj] = mat_id
        next_mat_id += 1
        lines.append(
            f"INSERT OR REPLACE INTO matieres (id, libelle) "
            f"VALUES ({mat_id}, {_sql_escape(subj)});"
        )
        for comp in comps:
            cid = next_comp_id
            competence_ids[(subj, comp)] = cid
            next_comp_id += 1
            lines.append(
                f"INSERT OR REPLACE INTO competences (id, matiere_id, libelle) "
                f"VALUES ({cid}, {mat_id}, {_sql_escape(comp)});"
            )
    lines.append("")

    # ---------- DONNEES : eleves ----------
    lines.append("-- 4) Insertion eleves")
    for s in students:
        lines.append(
            f"INSERT OR REPLACE INTO eleves "
            f"(matricule, nom_famille, prenom, nom_complet, date_naissance, "
            f"classe_code, annee_acad) VALUES ("
            f"{_sql_escape(s['matricule'])}, "
            f"{_sql_escape(s['nom_famille'])}, "
            f"{_sql_escape(s['prenom'])}, "
            f"{_sql_escape(s['nom_complet'])}, "
            f"{_sql_date(s['naissance'])}, "
            f"{_sql_escape(code)}, "
            f"{_sql_escape(academic_year)});"
        )
    lines.append("")

    # ---------- DONNEES : notes ----------
    lines.append("-- 5) Insertion notes")
    for s in students:
        for period_key, std in (("P1", s["p1"]), ("P2", s["p2"])):
            if not std:
                continue
            for subj, comps in std.get("data", {}).items():
                if subj not in matiere_ids:
                    continue
                for comp, periods in comps.items():
                    cid = competence_ids.get((subj, comp))
                    if cid is None:
                        continue
                    pdata = periods.get(period_key) or {}
                    val = pdata.get("value")
                    lvl = pdata.get("level")
                    if val is None and not lvl:
                        continue
                    lines.append(
                        f"INSERT OR REPLACE INTO notes "
                        f"(matricule, competence_id, periode, note, "
                        f"niveau_acquis, annee_acad) VALUES ("
                        f"{_sql_escape(s['matricule'])}, "
                        f"{cid}, "
                        f"{_sql_escape(period_key)}, "
                        f"{_sql_num(val)}, "
                        f"{_sql_escape(lvl) if lvl else 'NULL'}, "
                        f"{_sql_escape(academic_year)});"
                    )
    lines.append("")

    # ---------- DONNEES : totaux ----------
    lines.append("-- 6) Insertion totaux et moyennes")
    for s in students:
        for period_key, std in (("P1", s["p1"]), ("P2", s["p2"])):
            if not std:
                continue
            for tlabel, tdata in std.get("totaux", {}).items():
                v = tdata.get(period_key)
                if v is None:
                    continue
                lines.append(
                    f"INSERT OR REPLACE INTO totaux "
                    f"(matricule, libelle, periode, valeur, annee_acad) "
                    f"VALUES ("
                    f"{_sql_escape(s['matricule'])}, "
                    f"{_sql_escape(tlabel)}, "
                    f"{_sql_escape(period_key)}, "
                    f"{_sql_num(v)}, "
                    f"{_sql_escape(academic_year)});"
                )
    lines.append("")

    # ---------- DONNEES : appreciations + discipline ----------
    lines.append("-- 7) Insertion appreciations et discipline")
    for s in students:
        for period_key in ("P1", "P2"):
            std = s["p1"] if period_key == "P1" else s["p2"]
            if not std:
                continue
            disc = std.get("discipline", {}).get(period_key, "")
            obs = std.get("appreciations", {}).get(period_key, "")
            if not disc and not obs:
                continue
            lines.append(
                f"INSERT OR REPLACE INTO appreciations "
                f"(matricule, periode, discipline, observation, annee_acad) "
                f"VALUES ("
                f"{_sql_escape(s['matricule'])}, "
                f"{_sql_escape(period_key)}, "
                f"{_sql_escape(disc) if disc else 'NULL'}, "
                f"{_sql_escape(obs) if obs else 'NULL'}, "
                f"{_sql_escape(academic_year)});"
            )
    lines.append("")
    lines.append("-- Fin du script")
    lines.append("")

    return "\n".join(lines)

def write*sql_export(class_info, students_p1, students_p2,
schema, totaux_labels, target_path,
academic_year=ACADEMIC_YEAR, niveau=NIVEAU):
"""Ecrit le script SQL dans target_path."""
sql = build_sql_export(class_info, students_p1, students_p2,
schema, totaux_labels,
academic_year=academic_year, niveau=niveau)
target_path = Path(target_path)
target_path.parent.mkdir(parents=True, exist_ok=True)
try:
target_path.write_text(sql, encoding="utf-8")
return target_path
except PermissionError: # Meme strategie de fallback que pour les Excel
for i in range(2, 11):
alt = target_path.with_name(
f"{target_path.stem}\_v{i}{target_path.suffix}"
)
try:
alt.write_text(sql, encoding="utf-8")
return alt
except PermissionError:
continue
from datetime import datetime
stamp = datetime.now().strftime("%Y%m%d*%H%M%S")
alt = target*path.with_name(
f"{target_path.stem}*{stamp}{target_path.suffix}"
)
alt.write_text(sql, encoding="utf-8")
return alt

# ============================================================================

# STYLES EXCEL

# ============================================================================

HEADER_FILL = PatternFill(start_color="1F4E78", end_color="1F4E78", fill_type="solid")
PERIOD1_FILL = PatternFill(start_color="2E75B6", end_color="2E75B6", fill_type="solid")
PERIOD2_FILL = PatternFill(start_color="548235", end_color="548235", fill_type="solid")
SUBJECT_FILL = PatternFill(start_color="D9E1F2", end_color="D9E1F2", fill_type="solid")
COMP_FILL = PatternFill(start_color="EDEDED", end_color="EDEDED", fill_type="solid")
TOTAUX_FILL = PatternFill(start_color="FCE4D6", end_color="FCE4D6", fill_type="solid")
DISCIPLINE_FILL = PatternFill(start_color="FFF2CC", end_color="FFF2CC", fill_type="solid")
META_FILL = PatternFill(start_color="F2F2F2", end_color="F2F2F2", fill_type="solid")

A_FILL = PatternFill(start_color="C6EFCE", end_color="C6EFCE", fill_type="solid")
EVA_FILL = PatternFill(start_color="FFEB9C", end_color="FFEB9C", fill_type="solid")
NA_FILL = PatternFill(start_color="FFC7CE", end_color="FFC7CE", fill_type="solid")

WHITE_BOLD = Font(name="Arial", color="FFFFFF", bold=True, size=11)
BLACK_BOLD = Font(name="Arial", bold=True, size=10)
NORMAL_FONT = Font(name="Arial", size=10)
TITLE_FONT = Font(name="Arial", bold=True, size=14, color="FFFFFF")
SUBTITLE_FONT = Font(name="Arial", bold=True, size=10, color="000000", italic=True)
BORDER = Border(
left=Side(style="thin", color="999999"),
right=Side(style="thin", color="999999"),
top=Side(style="thin", color="999999"),
bottom=Side(style="thin", color="999999"),
)
CENTER = Alignment(horizontal="center", vertical="center", wrap_text=True)

# ============================================================================

# GENERATION EXCEL

# ============================================================================

def build_workbook(class_info: dict, students_p1, students_p2,
academic_year: str = ACADEMIC_YEAR,
niveau: str = NIVEAU):
"""
Construit un classeur Excel pour une classe.

    class_info contient au minimum :
        code, niveau, section
    """
    class_code = class_info["code"]
    class_niveau = class_info.get("niveau", niveau)
    class_section = class_info.get("section", SECTION)

    schema = build_class_schema(students_p1 + students_p2)
    totaux_labels = collect_totaux_labels(students_p1 + students_p2)
    if not schema:
        return None

    wb = Workbook()
    ws = wb.active
    ws.title = f"Synthese {class_code}"

    # --- Fusion des eleves par nom ---
    by_name = {}

    def _merge(std, period_key):
        key = normalize(std["nom"])
        entry = by_name.setdefault(key, {
            "nom": std["nom"],
            "nom_famille": std.get("nom_famille", ""),
            "prenom": std.get("prenom", ""),
            "matricule": std["matricule"],
            "naissance": std.get("naissance", ""),
            "p1": None, "p2": None,
        })
        entry[period_key] = std
        # Completer avec les meilleures donnees disponibles
        if not entry["nom_famille"] and std.get("nom_famille"):
            entry["nom_famille"] = std["nom_famille"]
        if not entry["prenom"] and std.get("prenom"):
            entry["prenom"] = std["prenom"]
        if (entry["matricule"].endswith("N/A")
                or entry["matricule"] == "N/A"
                or not entry["matricule"]):
            entry["matricule"] = std["matricule"]
        if std.get("naissance") and not entry["naissance"]:
            entry["naissance"] = std["naissance"]

    for std in students_p1:
        _merge(std, "p1")
    for std in students_p2:
        _merge(std, "p2")

    students = sorted(by_name.values(), key=lambda x: normalize(x["nom"]))

    # --- TITRE PRINCIPAL ---
    title = ws.cell(
        row=1, column=1,
        value=(f"CARNET INTEC - {niveau.upper()} - "
               f"NIVEAU {class_niveau} - CLASSE {class_code} - "
               f"SECTION {class_section.upper()} - "
               f"ANNEE ACADEMIQUE {academic_year}"),
    )
    title.font = TITLE_FONT
    title.fill = HEADER_FILL
    title.alignment = CENTER

    # --- COLONNES FIXES ---
    fixed_cols = [
        (1, "MATRICULE"),
        (2, "NOM"),
        (3, "PRENOM(S)"),
        (4, "NOM COMPLET"),
        (5, "DATE DE NAISSANCE"),
        (6, "NIVEAU"),
        (7, "CLASSE"),
        (8, "SECTION"),
    ]
    for col_idx, label in fixed_cols:
        # Ecrire la valeur dans la cellule TOP-LEFT (row 2) AVANT la fusion :
        # openpyxl conserve uniquement la valeur du coin haut-gauche apres
        # merge_cells, donc ecrire en row 4 puis fusionner 2:4 efface le label.
        c = ws.cell(row=2, column=col_idx, value=label)
        c.fill = HEADER_FILL
        c.font = WHITE_BOLD
        c.alignment = CENTER
        c.border = BORDER
        # Appliquer le style aux autres cellules du merge avant la fusion
        for r in (3, 4):
            cc = ws.cell(row=r, column=col_idx)
            cc.fill = HEADER_FILL
            cc.border = BORDER
        ws.merge_cells(start_row=2, start_column=col_idx,
                       end_row=4, end_column=col_idx)

    fixed_count = len(fixed_cols)

    # --- BLOCS PAR PERIODE ---
    def write_period_block(start_col, period_label, period_fill):
        col = start_col
        pcell = ws.cell(row=2, column=col, value=period_label)
        pcell.fill = period_fill
        pcell.font = WHITE_BOLD
        pcell.alignment = CENTER
        block_start = col

        for subj, comps in schema.items():
            subj_start = col
            for comp in comps:
                cc = ws.cell(row=4, column=col, value=comp)
                cc.fill = COMP_FILL
                cc.font = BLACK_BOLD
                cc.alignment = CENTER
                cc.border = BORDER
                col += 1
            sc = ws.cell(row=3, column=subj_start, value=subj)
            sc.fill = SUBJECT_FILL
            sc.font = BLACK_BOLD
            sc.alignment = CENTER
            sc.border = BORDER
            if col - 1 > subj_start:
                ws.merge_cells(start_row=3, start_column=subj_start,
                               end_row=3, end_column=col - 1)

        if totaux_labels:
            totaux_start = col
            for tlabel in totaux_labels:
                tc = ws.cell(row=4, column=col, value=tlabel)
                tc.fill = TOTAUX_FILL
                tc.font = BLACK_BOLD
                tc.alignment = CENTER
                tc.border = BORDER
                col += 1
            th = ws.cell(row=3, column=totaux_start, value="TOTAUX / MOYENNES")
            th.fill = TOTAUX_FILL
            th.font = BLACK_BOLD
            th.alignment = CENTER
            th.border = BORDER
            if col - 1 > totaux_start:
                ws.merge_cells(start_row=3, start_column=totaux_start,
                               end_row=3, end_column=col - 1)

        dh = ws.cell(row=3, column=col, value="DIM. PERS.")
        dh.fill = DISCIPLINE_FILL
        dh.font = BLACK_BOLD
        dh.alignment = CENTER
        dh.border = BORDER
        dc = ws.cell(row=4, column=col, value="DISCIPLINE")
        dc.fill = DISCIPLINE_FILL
        dc.font = BLACK_BOLD
        dc.alignment = CENTER
        dc.border = BORDER
        col += 1

        oc = ws.cell(row=3, column=col, value="OBSERVATIONS")
        oc.fill = SUBJECT_FILL
        oc.font = BLACK_BOLD
        oc.alignment = CENTER
        oc.border = BORDER
        # Pre-styliser row 4 avant la fusion 3:4
        oc4 = ws.cell(row=4, column=col)
        oc4.fill = SUBJECT_FILL
        oc4.border = BORDER
        ws.merge_cells(start_row=3, start_column=col, end_row=4, end_column=col)
        col += 1

        if col - 1 > block_start:
            ws.merge_cells(start_row=2, start_column=block_start,
                           end_row=2, end_column=col - 1)
        return col

    p1_start = fixed_count + 1
    p2_start = write_period_block(p1_start, "PERIODE 1 (Trimestre 1)", PERIOD1_FILL)
    end_col = write_period_block(p2_start, "PERIODE 2 (Trimestre 2)", PERIOD2_FILL)
    ws.merge_cells(start_row=1, start_column=1, end_row=1, end_column=end_col - 1)

    # --- DONNEES ELEVES ---
    def write_period_data(row, col, std, period_key):
        if std is None:
            for subj, comps in schema.items():
                for _ in comps:
                    ws.cell(row=row, column=col).border = BORDER
                    col += 1
            for _ in totaux_labels:
                ws.cell(row=row, column=col).border = BORDER
                col += 1
            ws.cell(row=row, column=col).border = BORDER
            col += 1
            c = ws.cell(row=row, column=col, value="- Periode non disponible -")
            c.border = BORDER
            c.font = Font(name="Arial", italic=True, color="888888")
            col += 1
            return col

        for subj, comps in schema.items():
            subj_data = std["data"].get(subj, {})
            for comp in comps:
                comp_data = subj_data.get(comp, {})
                pdata = comp_data.get(period_key, {}) if comp_data else {}
                val = pdata.get("value") if pdata else None
                lvl = pdata.get("level") if pdata else None

                c = ws.cell(row=row, column=col, value=val)
                c.border = BORDER
                c.alignment = CENTER
                c.font = NORMAL_FONT
                if val is not None:
                    if lvl == "A":
                        c.fill = A_FILL
                    elif lvl == "EVA":
                        c.fill = EVA_FILL
                    elif lvl == "NA":
                        c.fill = NA_FILL
                    else:
                        c.fill = COMP_FILL
                    c.font = BLACK_BOLD
                col += 1

        for tlabel in totaux_labels:
            tnorm = normalize(tlabel)
            val = None
            for k, v in std.get("totaux", {}).items():
                if normalize(k) == tnorm:
                    val = v.get(period_key)
                    break
            c = ws.cell(row=row, column=col, value=val)
            c.border = BORDER
            c.alignment = CENTER
            c.fill = TOTAUX_FILL
            c.font = BLACK_BOLD
            col += 1

        disc_val = std.get("discipline", {}).get(period_key, "")
        c = ws.cell(row=row, column=col, value=disc_val)
        c.border = BORDER
        c.alignment = CENTER
        c.fill = DISCIPLINE_FILL
        c.font = NORMAL_FONT
        col += 1

        obs_val = std.get("appreciations", {}).get(period_key, "")
        c = ws.cell(row=row, column=col, value=obs_val)
        c.border = BORDER
        c.alignment = Alignment(wrap_text=True, vertical="top",
                                horizontal="left")
        c.font = NORMAL_FONT
        col += 1
        return col

    for r_idx, info in enumerate(students, start=5):
        # Colonnes fixes (8)
        cells = [
            (1, info["matricule"], CENTER, NORMAL_FONT),
            (2, info.get("nom_famille", ""), Alignment(horizontal="left",
                                                        vertical="center"),
             BLACK_BOLD),
            (3, info.get("prenom", ""), Alignment(horizontal="left",
                                                   vertical="center"),
             NORMAL_FONT),
            (4, info["nom"], Alignment(horizontal="left",
                                        vertical="center"), BLACK_BOLD),
            (5, info.get("naissance", ""), CENTER, NORMAL_FONT),
            (6, class_niveau, CENTER, NORMAL_FONT),
            (7, class_code, CENTER, NORMAL_FONT),
            (8, class_section, CENTER, NORMAL_FONT),
        ]
        for col_idx, val, align, font in cells:
            c = ws.cell(row=r_idx, column=col_idx, value=val)
            c.border = BORDER
            c.alignment = align
            c.font = font

        write_period_data(r_idx, p1_start, info["p1"], "P1")
        write_period_data(r_idx, p2_start, info["p2"], "P2")
        ws.row_dimensions[r_idx].height = 42

    # --- DIMENSIONS ---
    ws.row_dimensions[1].height = 30
    ws.row_dimensions[2].height = 22
    ws.row_dimensions[3].height = 24
    ws.row_dimensions[4].height = 50
    ws.column_dimensions["A"].width = 18  # Matricule
    ws.column_dimensions["B"].width = 18  # Nom
    ws.column_dimensions["C"].width = 22  # Prenoms
    ws.column_dimensions["D"].width = 26  # Nom complet
    ws.column_dimensions["E"].width = 14  # Date naissance
    ws.column_dimensions["F"].width = 10  # Niveau
    ws.column_dimensions["G"].width = 10  # Classe
    ws.column_dimensions["H"].width = 14  # Section

    for c in range(fixed_count + 1, end_col):
        letter = get_column_letter(c)
        v3 = ws.cell(row=3, column=c).value
        v4 = ws.cell(row=4, column=c).value
        if v3 and "OBSERVATION" in str(v3).upper():
            ws.column_dimensions[letter].width = 38
        elif v4 and ("TOTAL" in str(v4).upper() or "MOYENNE" in str(v4).upper()):
            ws.column_dimensions[letter].width = 12
        elif v4 and "DISCIPLINE" in str(v4).upper():
            ws.column_dimensions[letter].width = 14
        else:
            ws.column_dimensions[letter].width = 11

    ws.freeze_panes = ws.cell(row=5, column=fixed_count + 1).coordinate

    # --- ONGLET METADONNEES ---
    meta = wb.create_sheet("Metadonnees", 1)
    meta.cell(row=1, column=1, value="METADONNEES DE LA CLASSE").font = TITLE_FONT
    meta.cell(row=1, column=1).fill = HEADER_FILL
    meta.cell(row=1, column=1).alignment = CENTER
    meta.merge_cells(start_row=1, start_column=1, end_row=1, end_column=2)

    meta_rows = [
        ("Annee academique", academic_year),
        ("Niveau d'enseignement", niveau),
        ("Niveau de la classe", class_niveau),
        ("Code classe", class_code),
        ("Section", class_section),
        ("Effectif total", len(students)),
        ("Effectif Trimestre 1", sum(1 for s in students if s["p1"])),
        ("Effectif Trimestre 2", sum(1 for s in students if s["p2"])),
        ("Nombre de matieres detectees", len(schema)),
        ("Nombre total de competences", sum(len(c) for c in schema.values())),
    ]
    for i, (k, v) in enumerate(meta_rows, start=3):
        kcell = meta.cell(row=i, column=1, value=k)
        kcell.font = BLACK_BOLD
        kcell.fill = META_FILL
        kcell.border = BORDER
        vcell = meta.cell(row=i, column=2, value=v)
        vcell.font = NORMAL_FONT
        vcell.border = BORDER

    # Detail des matieres et competences
    start = len(meta_rows) + 5
    h1 = meta.cell(row=start, column=1, value="MATIERE")
    h2 = meta.cell(row=start, column=2, value="COMPETENCES DETECTEES")
    for h in (h1, h2):
        h.font = WHITE_BOLD
        h.fill = HEADER_FILL
        h.alignment = CENTER
        h.border = BORDER
    for i, (subj, comps) in enumerate(schema.items(), start=start + 1):
        kc = meta.cell(row=i, column=1, value=subj)
        kc.font = BLACK_BOLD
        kc.fill = SUBJECT_FILL
        kc.border = BORDER
        vc = meta.cell(row=i, column=2, value=" | ".join(comps))
        vc.font = NORMAL_FONT
        vc.border = BORDER
        vc.alignment = Alignment(wrap_text=True, vertical="center")

    meta.column_dimensions["A"].width = 32
    meta.column_dimensions["B"].width = 70

    # --- LEGENDE ---
    leg = wb.create_sheet("Legende")
    leg.cell(row=1, column=1,
             value="LEGENDE - Code couleur des cellules de notes").font = TITLE_FONT
    leg.cell(row=1, column=1).fill = HEADER_FILL
    leg.cell(row=1, column=1).alignment = CENTER
    leg.merge_cells(start_row=1, start_column=1, end_row=1, end_column=4)

    leg.cell(row=3, column=1, value="Couleur").font = BLACK_BOLD
    leg.cell(row=3, column=2, value="Niveau").font = BLACK_BOLD
    leg.cell(row=3, column=3, value="Signification").font = BLACK_BOLD
    leg.cell(row=3, column=4, value="Plage de notes").font = BLACK_BOLD
    for c_idx in range(1, 5):
        leg.cell(row=3, column=c_idx).fill = SUBJECT_FILL
        leg.cell(row=3, column=c_idx).border = BORDER
        leg.cell(row=3, column=c_idx).alignment = CENTER

    rows_leg = [
        (A_FILL,    "A",   "Acquis - (Tres) satisfaisant",  "7 a 10 / 10"),
        (EVA_FILL,  "EVA", "En voie d'acquisition - Moyen",  "4 a 6 / 10"),
        (NA_FILL,   "NA",  "Non acquis - Insuffisant",       "0 a 3 / 10"),
        (COMP_FILL, "-",   "Niveau non precise (DICTEE)",    "Note seule"),
    ]
    for i, (fill, lvl, desc, plage) in enumerate(rows_leg, start=4):
        leg.cell(row=i, column=1, value="").fill = fill
        leg.cell(row=i, column=1).border = BORDER
        leg.cell(row=i, column=2, value=lvl).font = BLACK_BOLD
        leg.cell(row=i, column=2).alignment = CENTER
        leg.cell(row=i, column=2).border = BORDER
        leg.cell(row=i, column=3, value=desc).border = BORDER
        leg.cell(row=i, column=3).font = NORMAL_FONT
        leg.cell(row=i, column=4, value=plage).border = BORDER
        leg.cell(row=i, column=4).alignment = CENTER
        leg.cell(row=i, column=4).font = NORMAL_FONT

    leg.column_dimensions["A"].width = 12
    leg.column_dimensions["B"].width = 10
    leg.column_dimensions["C"].width = 38
    leg.column_dimensions["D"].width = 18

    return wb

# ============================================================================

# DECOUVERTE DES FICHIERS

# ============================================================================

def find_class_folder(base_dir, configured_name):
"""
Cherche un dossier de classe dans `base_dir` de facon RECURSIVE et
tolerante (insensible a la casse, aux accents, et aux espaces).

    Retourne le 1er dossier dont le nom :
      - correspond exactement (apres normalisation), OU
      - contient tous les "tokens" significatifs du nom configure
        (par exemple "CARNET", "CPA", "TRIM 2" pour "CARNET CPA -Trim 2-...")
    """
    if not configured_name:
        return None
    base = Path(base_dir)
    if not base.exists():
        return None

    target = normalize(configured_name)
    target_tokens = [t for t in re.split(r"[\s\-_]+", target) if len(t) > 1]

    # 1) Essai direct (cas ou le configured_name est un chemin relatif valide)
    direct = base / configured_name
    if direct.is_dir():
        return direct

    # 2) Parcours recursif - on retient le meilleur candidat
    exact_match = None
    best_match = None
    best_score = 0

    for p in base.rglob("*"):
        if not p.is_dir():
            continue
        name_norm = normalize(p.name)

        # Match exact apres normalisation
        if name_norm == target:
            exact_match = p
            break

        # Match par tokens : on compte combien de tokens du target sont
        # presents dans le nom du dossier candidat
        if target_tokens:
            score = sum(1 for tok in target_tokens if tok in name_norm)
            # Il faut qu'AU MOINS la moitie des tokens (et au moins 2)
            # soient presents pour considerer comme candidat valable
            min_required = max(2, len(target_tokens) // 2)
            if score >= min_required and score > best_score:
                best_score = score
                best_match = p

    return exact_match or best_match

def collect_docx(folder):
if folder is None or not Path(folder).exists():
return []
folder = Path(folder)

    docx_files = sorted(f for f in folder.rglob("*.docx") if not f.name.startswith("~$"))
    doc_files = sorted(
        f for f in folder.rglob("*.doc")
        if not f.name.startswith("~$") and f.suffix.lower() == ".doc"
    )

    if doc_files:
        existing = {f.stem.lower() for f in docx_files}
        to_convert = [f for f in doc_files if f.stem.lower() not in existing]
        if to_convert:
            print(f"     [.doc] {len(to_convert)} fichier(s) a convertir en .docx...")
            converted = convert_doc_to_docx(to_convert)
            if converted:
                print(f"     [.doc] {len(converted)} conversion(s) reussie(s).")
                docx_files = sorted(
                    f for f in folder.rglob("*.docx")
                    if not f.name.startswith("~$")
                )
            else:
                print("     [.doc] /!\\ Conversion auto impossible (pywin32 + Word requis).")

    return docx_files

def convert_doc_to_docx(doc_paths):
converted = []
word = None
try:
try:
import win32com.client
except ImportError:
return converted
WD_FORMAT_DOCX = 12
word = win32com.client.DispatchEx("Word.Application")
word.Visible = False
word.DisplayAlerts = 0
for doc_path in doc_paths:
doc_path = Path(doc_path)
new_path = doc_path.with_suffix(".docx")
try:
wdoc = word.Documents.Open(str(doc_path.resolve()), ReadOnly=True)
wdoc.SaveAs2(str(new_path.resolve()), FileFormat=WD_FORMAT_DOCX)
wdoc.Close(SaveChanges=False)
converted.append(new_path)
except Exception as e:
print(f" [.doc] erreur {doc_path.name}: {e}")
except Exception as e:
print(f" [.doc] /!\\ Word non disponible : {e}")
finally:
if word is not None:
try:
word.Quit()
except Exception:
pass
return converted

# ============================================================================

# MAIN

# ============================================================================

def \_split_student_by_period(std):
"""
Un carnet de Trimestre 2 contient en general DEUX series de notes par
competence (P1 et P2) cote a cote dans le meme tableau. La fonction
parse_competence_row() les a deja separees en "P1" et "P2" dans
std["data"][matiere][competence].

    Cette fonction reconstruit deux "vues" du meme eleve :
        - p1_view : seulement les valeurs P1 (re-mappees sous la cle "P1")
        - p2_view : seulement les valeurs P2 (re-mappees sous la cle "P1"
                    pour rester compatible avec build_workbook qui ecrit
                    chaque periode en lisant la cle "P1" de la vue passee)

    Mais en realite build_workbook attend que chaque eleve garde toutes ses
    periodes : il lit explicitement std["data"][...]["P1"] pour la colonne
    P1 et std["data"][...]["P2"] pour la colonne P2.

    On a donc deux choix :
       (a) passer le MEME eleve dans students_p1 et students_p2
           => mais alors build_workbook va le fusionner en une seule ligne
              et lire "P1" pour la colonne P1 et "P2" pour la colonne P2
              => CORRECT, c'est exactement ce qu'on veut.

    Conclusion : pas besoin de split du tout. On passe simplement le meme
    eleve dans students_p1 ET students_p2 si le carnet contient au moins
    une note pour chaque periode. La fonction _merge dans build_workbook
    fait le bon travail.

    Cette fonction retourne (has_p1, has_p2) en testant si l'eleve a au
    moins une note non vide dans P1 et/ou P2.
    """
    has_p1 = False
    has_p2 = False
    for subj, comps in std.get("data", {}).items():
        for comp, periods in comps.items():
            p1d = periods.get("P1") or {}
            p2d = periods.get("P2") or {}
            if p1d.get("value") is not None:
                has_p1 = True
            if p2d.get("value") is not None:
                has_p2 = True
            if has_p1 and has_p2:
                return has_p1, has_p2
    # Verifier aussi les totaux et les appreciations
    for v in std.get("totaux", {}).values():
        if v.get("P1") is not None:
            has_p1 = True
        if v.get("P2") is not None:
            has_p2 = True
    if std.get("appreciations", {}).get("P1"):
        has_p1 = True
    if std.get("appreciations", {}).get("P2"):
        has_p2 = True
    if std.get("discipline", {}).get("P1"):
        has_p1 = True
    if std.get("discipline", {}).get("P2"):
        has_p2 = True
    return has_p1, has_p2

def process_class(cls):
"""
Lit les carnets de Trimestre 2 d'une classe.

    IMPORTANT : chaque carnet de Trimestre 2 contient INTERNEMENT les notes
    de la Periode 1 ET de la Periode 2 (les tableaux ont 6+ colonnes :
    A/EVA/NA pour P1 puis A/EVA/NA pour P2). On lit donc UN SEUL fichier
    par eleve, et l'eleve apparait dans students_p1 ET students_p2 si le
    carnet contient des notes pour les deux periodes.

    Pour CP B, un dossier Trim 1 separe peut aussi exister : on lit alors
    AUSSI ces fichiers (ils n'ont generalement que P1 a l'interieur) pour
    completer les eleves dont le carnet de T2 n'aurait pas les colonnes P1.
    """
    code = cls["code"]
    print(f"\n{'='*70}\n  CLASSE : {code} (niveau {cls.get('niveau','?')}, "
          f"section {cls.get('section','?')})\n{'='*70}")

    # --- Lecture des carnets de Trim 2 (contiennent P1 + P2 internes) ---
    p2_folder = find_class_folder(BASE_TRIM2, cls["trim2_dir"])
    p2_files = collect_docx(p2_folder)
    print(f"  Carnets Trim 2 : {p2_folder if p2_folder else '(aucun dossier)'}")
    print(f"                  -> {len(p2_files)} fichier(s)")

    p1_students = []
    p2_students = []
    for f in p2_files:
        try:
            std = extract_student_data(f, code)
            has_p1, has_p2 = _split_student_by_period(std)
            if has_p1:
                p1_students.append(std)
            if has_p2:
                p2_students.append(std)
            tag = f"P1+P2" if (has_p1 and has_p2) else ("P1" if has_p1 else ("P2" if has_p2 else "vide"))
            print(f"     [{tag:5s}] OK   {f.name}")
        except Exception as e:
            print(f"     [ERR ] {f.name} : {e}")

    # --- Trim 1 separe : seulement si configure (en pratique : CP B) ---
    if cls.get("trim1_dir"):
        p1_folder = find_class_folder(BASE_TRIM1, cls["trim1_dir"])
        p1_files = collect_docx(p1_folder)
        print(f"  Carnets Trim 1 : {p1_folder if p1_folder else '(aucun dossier)'}")
        print(f"                  -> {len(p1_files)} fichier(s)")

        # Indexer les eleves Trim 2 par nom pour completer leur P1 si vide
        existing_by_name = {normalize(s["nom"]): s for s in p2_students}

        for f in p1_files:
            try:
                std = extract_student_data(f, code)
                key = normalize(std["nom"])
                # Si l'eleve a deja un carnet T2 ET qu'il n'a pas de P1
                # interne, on prend le P1 de ce carnet T1 separe.
                if key in existing_by_name:
                    existing = existing_by_name[key]
                    has_p1_existing, _ = _split_student_by_period(existing)
                    if not has_p1_existing:
                        # Reporter les notes/totaux/observations P1 du
                        # carnet T1 dans l'objet existant.
                        for subj, comps in std.get("data", {}).items():
                            existing.setdefault("data", OrderedDict())
                            existing["data"].setdefault(subj, OrderedDict())
                            for comp, periods in comps.items():
                                existing["data"][subj].setdefault(comp, {})
                                # Lire P1 du carnet T1 (qui peut etre dans
                                # P1, ou meme dans P2 si le T1 utilise une
                                # autre disposition - on prend la 1ere note)
                                src = periods.get("P1") or periods.get("P2") or {}
                                existing["data"][subj][comp]["P1"] = src
                        for tk, tv in std.get("totaux", {}).items():
                            existing.setdefault("totaux", OrderedDict())
                            existing["totaux"].setdefault(tk, {"P1": None, "P2": None, "P3": None})
                            v_p1 = tv.get("P1") if tv.get("P1") is not None else tv.get("P2")
                            if v_p1 is not None and existing["totaux"][tk].get("P1") is None:
                                existing["totaux"][tk]["P1"] = v_p1
                        for pk in ("P1",):
                            v = std.get("discipline", {}).get(pk)
                            if v and not existing.get("discipline", {}).get(pk):
                                existing.setdefault("discipline", {"P1": "", "P2": "", "P3": ""})
                                existing["discipline"][pk] = v
                            v = std.get("appreciations", {}).get(pk)
                            if v and not existing.get("appreciations", {}).get(pk):
                                existing.setdefault("appreciations", {"P1": "", "P2": "", "P3": ""})
                                existing["appreciations"][pk] = v
                        # S'assurer qu'il apparait dans p1_students
                        if existing not in p1_students:
                            p1_students.append(existing)
                else:
                    # Eleve absent du Trim 2 : on l'ajoute uniquement en P1
                    p1_students.append(std)
                print(f"     [T1  ] OK   {f.name}")
            except Exception as e:
                print(f"     [T1  ] ERR  {f.name} : {e}")
    else:
        print("  Carnets Trim 1 : (non configures pour cette classe)")

    if not p1_students and not p2_students:
        print(f"  /!\\ Aucun eleve trouve pour {code}, fichier non genere.")
        return None

    schema = build_class_schema(p1_students + p2_students)
    print(f"\n  Schema detecte ({len(schema)} matiere(s)) :")
    for subj, comps in schema.items():
        print(f"     - {subj} : {comps}")

    wb = build_workbook(cls, p1_students, p2_students,
                        academic_year=ACADEMIC_YEAR, niveau=NIVEAU)
    if wb is None:
        print("  /!\\ Aucune matiere detectee, fichier non genere.")
        return None

    out_dir = Path(OUTPUT_DIR)
    out_dir.mkdir(parents=True, exist_ok=True)
    out_path = out_dir / f"Extraction_{code}_P1_P2_{ACADEMIC_YEAR}.xlsx"
    actual_path = _safe_save_workbook(wb, out_path)
    print(f"\n  Fichier Excel : {actual_path}")

    # --- Export SQL ---
    sql_path = out_dir / f"Extraction_{code}_P1_P2_{ACADEMIC_YEAR}.sql"
    totaux_labels = collect_totaux_labels(p1_students + p2_students)
    actual_sql = write_sql_export(
        cls, p1_students, p2_students, schema, totaux_labels,
        sql_path, academic_year=ACADEMIC_YEAR, niveau=NIVEAU,
    )
    print(f"  Fichier SQL   : {actual_sql}")
    print(f"  Eleves : {len(p1_students)} avec P1, {len(p2_students)} avec P2")
    return actual_path

def main():
print("=" _ 70)
print(f" EXTRACTION CARNETS INTEC - {NIVEAU.upper()} {ACADEMIC_YEAR}")
print(" Extraction complete : notes + niveaux + totaux + appreciations")
print(" (CP B = Trim 1 + Trim 2 / autres classes = Trim 2 seul)")
print("=" _ 70)

    if not Path(BASE_TRIM1).exists() and not Path(BASE_TRIM2).exists():
        print("\n/!\\ Aucun dossier de base n'existe.")
        sys.exit(1)

    generated = []
    for cls in CLASSES:
        try:
            out = process_class(cls)
            if out:
                generated.append(out)
        except Exception as e:
            print(f"\n/!\\ ERREUR sur la classe {cls['code']} : {e}")
            traceback.print_exc()

    print("\n" + "=" * 70)
    print(f"  TERMINE - {len(generated)} fichier(s) genere(s)")
    print("=" * 70)
    for p in generated:
        print(f"   - {p}")

if **name** == "**main**":
main()
