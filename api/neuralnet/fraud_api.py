from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import numpy as np
import tensorflow as tf
import pickle

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost"],
    allow_credentials=True,
    allow_methods=["POST"],
    allow_headers=["Content-Type"],
)

class FraudInput(BaseModel):
    time_diff: float
    votes_per_user: int
    vpn_usage: int
    multiple_logins: int
    session_duration: float
    geo_location: int

model = tf.keras.models.load_model('fraud_model.keras')
with open('scaler.pkl', 'rb') as f:
    scaler = pickle.load(f)

@app.post("/predict")
async def predict_fraud(input: FraudInput):
    try:
        if input.time_diff < 0 or input.votes_per_user < 0 or input.session_duration < 0:
            raise HTTPException(status_code=400, detail="Negative values are not allowed")
        if input.vpn_usage not in [0, 1] or input.geo_location not in [0, 1, 2, 3, 4]:
            raise HTTPException(status_code=400, detail="Invalid categorical values")
        if input.votes_per_user > 10 or input.multiple_logins > 10:
            raise HTTPException(status_code=400, detail="Excessive vote or login count")

        features = np.array([[
            input.time_diff,
            input.votes_per_user,
            input.vpn_usage,
            input.multiple_logins,
            input.session_duration,
            input.geo_location
        ]])
        features_scaled = scaler.transform(features)

        fraud_proba = model.predict(features_scaled)[0][0]
        fraud_label = int(fraud_proba > 0.5)

        return {
            "fraud_label": fraud_label,
            "fraud_probability": float(fraud_proba)
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Prediction error: {str(e)}")