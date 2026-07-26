# Abhipraya survey question types

Use one `report_type` for every question. The type controls validation, storage interpretation, and dashboard visualisation.

| Type | Use for | Dashboard output |
|---|---|---|
| `rating` | Satisfaction, cleanliness, courtesy, waiting time | Average score and stars |
| `binary` | Yes/no or positive/negative questions | Positive and negative percentages |
| `category` | One choice from named categories | Category counts and distribution |
| `multi_category` | Select all applicable options | Count and percentage per option |
| `numeric` | Amount, quantity, count, distance | Total, average, minimum, maximum |
| `text` | Comments, suggestions, explanations | Response count and moderated comments |
| `duration` | Waiting time or service duration | Average, median, and time bands |
| `date` | Visit date or incident date | Date-range distribution |
| `availability` | Available/unavailable service or medicine | Available vs unavailable percentage |
| `severity` | Low, medium, high, critical issue | Priority distribution and alerts |
| `demographic` | Age, gender, beneficiary type, language | Aggregated demographic breakdown |
| `consent` | Permission or acknowledgement | Consent vs declined percentage |
| `ranking` | Rank services or priorities | Average rank and priority order |
| `matrix_rating` | Rate several indicators in one question | Indicator-wise averages |
| `file` | Optional evidence upload | Submission count and review queue |

## Recommended configuration patterns

### Rating

```json
{
  "qn": 1,
  "lang": "1",
  "ques": "How would you rate cleanliness?",
  "report_type": "rating",
  "scale": 5,
  "options": [
    {"value": 1, "text": "Very poor"},
    {"value": 2, "text": "Poor"},
    {"value": 3, "text": "Average"},
    {"value": 4, "text": "Good"},
    {"value": 5, "text": "Very good"}
  ]
}
```

### Binary

```json
{
  "qn": 2,
  "lang": "1",
  "ques": "Did you have to pay extra money?",
  "report_type": "binary",
  "options": [
    {"value": 1, "text": "Yes", "sentiment": "negative"},
    {"value": 2, "text": "No", "sentiment": "positive"}
  ],
  "positive_values": [2],
  "negative_values": [1]
}
```

### Category

```json
{
  "qn": 3,
  "lang": "1",
  "ques": "On which service did you spend money?",
  "report_type": "category",
  "options": [
    {"value": 1, "text": "Investigation"},
    {"value": 2, "text": "Medicines"},
    {"value": 3, "text": "Blood"},
    {"value": 4, "text": "Service provider"},
    {"value": 5, "text": "Other"}
  ]
}
```

### Numeric amount

```json
{
  "qn": 4,
  "lang": "1",
  "ques": "How much did you pay?",
  "report_type": "numeric",
  "unit": "INR",
  "min": 0,
  "max": 100000
}
```

### Multiple selection

```json
{"qn":5,"lang":"1","ques":"Which problems did you face?","report_type":"multi_category","options":[{"value":1,"text":"Long waiting time"},{"value":2,"text":"Medicine unavailable"},{"value":3,"text":"Staff unavailable"},{"value":4,"text":"Cleanliness"}]}
```

### Text feedback

```json
{"qn":6,"lang":"1","ques":"Please share any suggestion.","report_type":"text","required":false,"max_length":500}
```

### Duration

```json
{"qn":7,"lang":"1","ques":"How long did you wait?","report_type":"duration","unit":"minutes","min":0,"max":1440}
```

### Date

```json
{"qn":8,"lang":"1","ques":"When did you visit the facility?","report_type":"date","min":"2026-01-01","max":"today"}
```

### Availability

```json
{"qn":9,"lang":"1","ques":"Was the prescribed medicine available?","report_type":"availability","options":[{"value":1,"text":"Available","status":"available"},{"value":2,"text":"Not available","status":"unavailable"}]}
```

### Severity

```json
{"qn":10,"lang":"1","ques":"How serious was the issue?","report_type":"severity","options":[{"value":1,"text":"Low"},{"value":2,"text":"Medium"},{"value":3,"text":"High"},{"value":4,"text":"Critical"}]}
```

### Demographic

```json
{"qn":11,"lang":"1","ques":"What is your age group?","report_type":"demographic","dimension":"age_group","options":[{"value":1,"text":"10–20"},{"value":2,"text":"21–30"},{"value":3,"text":"31–40"},{"value":4,"text":"41–50"},{"value":5,"text":"51–60"},{"value":6,"text":"60+"},{"value":7,"text":"Prefer not to say"}]}
```

### Consent

```json
{"qn":12,"lang":"1","ques":"May the programme team use this anonymous response for service improvement?","report_type":"consent","options":[{"value":1,"text":"I agree","consent":true},{"value":2,"text":"I do not agree","consent":false}]}
```

### Ranking

```json
{"qn":13,"lang":"1","ques":"Rank the services that need improvement first.","report_type":"ranking","options":[{"value":1,"text":"Medicine availability"},{"value":2,"text":"Waiting time"},{"value":3,"text":"Cleanliness"},{"value":4,"text":"Staff courtesy"}]}
```

### Matrix rating

```json
{"qn":14,"lang":"1","ques":"Rate each aspect of your visit.","report_type":"matrix_rating","scale":5,"items":[{"id":"cleanliness","text":"Cleanliness"},{"id":"courtesy","text":"Staff courtesy"},{"id":"waiting","text":"Waiting time"}],"options":[{"value":1,"text":"Very poor"},{"value":2,"text":"Poor"},{"value":3,"text":"Average"},{"value":4,"text":"Good"},{"value":5,"text":"Very good"}]}
```

### File evidence

```json
{"qn":15,"lang":"1","ques":"Upload an optional supporting document or image.","report_type":"file","required":false,"accept":["image/jpeg","image/png","application/pdf"],"max_size_mb":5}
```

## Authoring rules

- Keep `qn` unique within each language and survey context.
- Keep the same `qn` across translations of the same question.
- Do not use `rating` for yes/no, category, amount, or text questions.
- Give every option a stable numeric `value`; never use array position as a hidden meaning.
- Define `sentiment` or positive/negative values for binary questions.
- Define `unit`, `min`, and `max` for numeric questions.
- Mark sensitive questions as optional where appropriate.
- Avoid collecting personally identifying information in public feedback.
- Use `icon` only for presentation; analytics must rely on `report_type`.
- Test every new question with a sample response before publishing a QR code.
