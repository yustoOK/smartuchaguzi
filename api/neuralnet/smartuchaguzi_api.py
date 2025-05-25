import pandas as pd
import tensorflow as tf
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import uvicorn
import hashlib

# FastAPI Setup
app = FastAPI()

class FraudInput(BaseModel):
    time_diff: int
    votes_per_user: int
    vpn_usage: int
    multiple_logins: int
    session_duration: int
    geo_location: int
    device_fingerprint: str
    ip_history: list
    vote_pattern: float
    user_behavior: int

@app.post("/predict")
async def predict_fraud(input_data: FraudInput):
    try:
        import pickle
        with open('scaler.pkl', 'rb') as f:
            scaler = pickle.load(f)
        with open('encoder.pkl', 'rb') as f:
            encoder = pickle.load(f)
        
        input_dict = input_data.dict()
        input_df = pd.DataFrame([input_dict])
        
        numerical_features = ['time_diff', 'votes_per_user', 'session_duration', 'vote_pattern', 'user_behavior']
        input_df[numerical_features] = scaler.transform(input_df[numerical_features])
        
        geo_encoded = encoder.transform(input_df[['geo_location']])
        geo_columns = [f'geo_{i}' for i in range(5)]
        input_df[geo_columns] = geo_encoded
        
        input_df['ip_count'] = input_df['ip_history'].apply(len)
        input_df['device_hash'] = input_df['device_fingerprint'].apply(lambda x: int(hashlib.md5(x.encode()).hexdigest(), 16) % 1000)
        
        input_df = input_df.drop(columns=['geo_location', 'device_fingerprint', 'ip_history'])
        
        model = tf.keras.models.load_model('fraud_detection_model.keras')
        
        proba = model.predict(input_df, verbose=0)[0][0]
        label = 1 if proba > 0.5 else 0
        
        return {
            "fraud_label": label,
            "fraud_probability": float(proba)
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    uvicorn.run(app, host="127.0.0.1", port=8002)