"""Generate the Hindi narration MP3s for the OPD star-rating demo page."""

import asyncio
import json
from pathlib import Path

import edge_tts


QUESTIONS = [
    "आप अस्पताल में सूचना की उपलब्धता, जैसे साइनबोर्ड और विभिन्न योजनाओं के विवरण को कैसे आंकेंगे?",
    "पंजीकरण काउंटर पर प्रतीक्षा समय का आप कैसे मूल्यांकन करेंगे?",
    "आप अस्पताल के कर्मचारियों के रवैये और व्यवहार का कैसे आँकलन करेंगे?",
    "आप प्रतीक्षा क्षेत्र में कुर्सियों, पंखों, पीने के पानी और बाथरूम और शौचालयों की सफाई सहित सुविधाओं का कैसे आँकलन करेंगे?",
    "डॉक्टरों के रवैये और संचार का कैसे आँकलन करेंगे?",
    "आप डॉक्टरों द्वारा परामर्श, जांच और काउंसलिंग पर दिए गए समय का कैसे आँकलन करेंगे?",
    "आप अस्पताल के भीतर लैब और रेडियोलॉजी जांच सुविधाओं की उपलब्धता का कैसे आँकलन करेंगे?",
    "आप दवा वितरण काउंटर पर तत्परता का कैसे आँकलन करेंगे?",
    "आप अस्पताल की डिस्पेंसरी में निर्धारित दवाओं की उपलब्धता का कैसे आँकलन करेंगे?",
    "अस्पताल में अपनी यात्रा के दौरान कुल मिलाकर आपकी संतुष्टि का स्तर क्या था?",
]
ENGLISH_QUESTIONS = [
    "How would you rate the availability of information in the hospital, such as signboards and details of various schemes?",
    "How would you rate the waiting time at the registration counter?",
    "How would you rate the attitude and behaviour of hospital staff?",
    "How would you rate the amenities in the waiting area, including chairs, fans, drinking water, and cleanliness of bathrooms and toilets?",
    "How would you rate the attitude and communication of the doctors?",
    "How would you rate the time spent by doctors on consultation, examination, and counselling?",
    "How would you rate the availability of lab and radiology investigation facilities within the hospital?",
    "How would you rate the promptness at the medicine distribution counter?",
    "How would you rate the availability of prescribed drugs at the hospital dispensary?",
    "Overall, how satisfied were you during your visit to the hospital?",
]
PROJECT_ROOT = Path(__file__).parents[1]
OPD_SURVEY_DIR = PROJECT_ROOT / "api" / "masters" / "surveys" / "department_4"
OPD_MANIFEST = json.loads((OPD_SURVEY_DIR / "manifest.json").read_text(encoding="utf-8"))
ACTIVE_VERSION = str(OPD_MANIFEST["active_version"])
MASTER_QUESTIONS = json.loads(
    (OPD_SURVEY_DIR / f"v{ACTIVE_VERSION}" / "survey.json").read_text(encoding="utf-8")
)
QUESTIONS = [item["ques"] for item in MASTER_QUESTIONS if item.get("lang") == "2" and int(item.get("qn", 0)) <= 10]
ENGLISH_QUESTIONS = [item["ques"] for item in MASTER_QUESTIONS if item.get("lang") == "1" and int(item.get("qn", 0)) <= 10]
# Say "स्टार" only once; the following numbers inherit that rating context.
RATING_GUIDANCE = "एक स्टार बहुत बुरा, दो सामान्य, तीन अच्छा, चार बहुत अच्छा, और पाँच उत्कृष्ट।"
FIRST_GUIDANCE = "दोबारा सुनने के लिए स्पीकर बटन दबाएं। आगे बढ़ने के लिए हरा बटन दबाएं।"
NEXT_GUIDANCE = "दोबारा सुनने के लिए स्पीकर बटन दबाएं। आगे बढ़ने के लिए हरा बटन दबाएं। पीछे जाने के लिए पीला बटन दबाएं।"
ENGLISH_RATING_GUIDANCE = "One star is very poor, two average, three good, four very good, and five excellent."
ENGLISH_FIRST_GUIDANCE = "To listen again, press the speaker button. To continue, press the green button."
ENGLISH_NEXT_GUIDANCE = "To listen again, press the speaker button. To continue, press the green button. To go back, press the yellow button."
ENGLISH_RATINGS = ["Very Poor", "Average", "Good", "Very Good", "Excellent"]
RATINGS = ["बहुत बुरा", "सामान्य", "अच्छा", "बहुत अच्छा", "उत्कृष्ट"]
OUTPUT_DIR = Path(__file__).parents[1] / "ui" / "assets" / "audio" / "opd-demo" / "hi"


async def generate(text: str, output: Path, voice: str | None = None) -> None:
    voice = voice or ("en-IN-NeerjaNeural" if "opd-demo/en" in output.as_posix() else "hi-IN-SwaraNeural")
    await edge_tts.Communicate(text, voice=voice).save(str(output))
    print(output)


async def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    tasks = []
    for index, question in enumerate(QUESTIONS, start=1):
        output = OUTPUT_DIR / f"Q{index}.mp3"
        guidance = FIRST_GUIDANCE if index == 1 else NEXT_GUIDANCE
        tasks.append(generate(f"{question} {RATING_GUIDANCE} {guidance}", output))
    for index, rating in enumerate(RATINGS, start=1):
        output = OUTPUT_DIR / f"rating-{index}.mp3"
        tasks.append(generate(f"आपने {index} स्टार, {rating} चुना है। {NEXT_GUIDANCE}", output))
        output = OUTPUT_DIR / f"first-rating-{index}.mp3"
        tasks.append(generate(f"आपने {index} स्टार, {rating} चुना है। {FIRST_GUIDANCE}", output))
    tasks.append(generate(
        "कृपया अपनी भाषा चुनें। हिंदी के लिए हरा बटन दबाएं। दोबारा सुनने के लिए स्पीकर बटन दबाएं।",
        OUTPUT_DIR / "language-select-hi.mp3",
    ))
    tasks.append(generate(
        "For English, press the orange button. To listen again, press the speaker button.",
        OUTPUT_DIR / "language-select-en.mp3",
        "en-IN-NeerjaNeural",
    ))
    english_dir = OUTPUT_DIR.parent / "en"
    english_dir.mkdir(parents=True, exist_ok=True)
    for index, question in enumerate(ENGLISH_QUESTIONS, start=1):
        guidance = ENGLISH_FIRST_GUIDANCE if index == 1 else ENGLISH_NEXT_GUIDANCE
        tasks.append(generate(f"{question} {ENGLISH_RATING_GUIDANCE} {guidance}", english_dir / f"Q{index}.mp3"))
    for index, rating in enumerate(ENGLISH_RATINGS, start=1):
        tasks.append(generate(f"You selected {index} stars, {rating}. {ENGLISH_NEXT_GUIDANCE}", english_dir / f"rating-{index}.mp3"))
        tasks.append(generate(f"You selected {index} stars, {rating}. {ENGLISH_FIRST_GUIDANCE}", english_dir / f"first-rating-{index}.mp3"))
    await asyncio.gather(*tasks)


if __name__ == "__main__":
    asyncio.run(main())
