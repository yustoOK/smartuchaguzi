FROM python:3.9-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY fraud_detection_model.keras .
COPY scaler.pkl .
COPY encoder.pkl .
COPY synthetic_voting_data.csv .
COPY smartuchaguzi_api.py .
COPY all_metrics_measures.py .

EXPOSE 8002

CMD ["uvicorn", "smartuchaguzi_api:app", "--host", "0.0.0.0", "--port", "8002"]