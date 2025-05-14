from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import numpy as np
from sklearn.ensemble import RandomForestClassifier
import pickle
import os

app = FastAPI()

# CORS setup for process-vote.php
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost"],  # Adjust for production
    allow_credentials=True,
    allow_methods=["POST"],
    allow_headers=["Content-Type"],
)

# Input model for fraud detection
class FraudInput(BaseModel):
    time_diff: float
    votes_per_user: int
    vpn_usage: int
    multiple_logins: int
    session_duration: float
    geo_location: int

# Load or train model
MODEL_PATH = "fraud_model.pkl"

def train_model():
    # Simulate loading synthetic dataset (replace with actual dataset loading)
    np.random.seed(42)
    X = []
    y = []
    for _ in range(50000):
        is_fraud = np.random.random() < 0.03
        if is_fraud:
            X.append([
                max(0.01, np.random.normal(2, 1.5)),
                max(1, np.random.poisson(5) + 1),
                np.random.choice([0, 1], p=[0.4, 0.6]),
                max(1, np.random.poisson(2) + 1),
                max(10, np.random.normal(45, 20)),
                np.random.choice([0, 4], p=[0.7, 0.3])
            ])
            y.append(1)
        else:
            X.append([
                max(0.01, np.random.normal(8, 3)),
                max(1, np.random.poisson(3) + 1),
                np.random.choice([0, 1], p=[0.95, 0.05]),
                max(1, np.random.poisson(1) + 1),
                max(10, np.random.normal(100, 30)),
                np.random.choice([0, 1, 2, 3, 4], p=[0.85, 0.05, 0.05, 0.03, 0.02])
            ])
            y.append(0)
    X = np.array(X)
    y = np.array(y)

    model = RandomForestClassifier(n_estimators=100, random_state=42)
    model.fit(X, y)
    with open(MODEL_PATH, 'wb') as f:
        pickle.dump(model, f)
    return model

if os.path.exists(MODEL_PATH):
    with open(MODEL_PATH, 'rb') as f:
        model = pickle.load(f)
else:
    model = train_model()

@app.post("/predict")
async def predict_fraud(input: FraudInput):
    try:
        # Validate inputs
        if input.time_diff < 0 or input.votes_per_user < 0 or input.session_duration < 0:
            raise HTTPException(status_code=400, detail="Negative values are not allowed")
        if input.vpn_usage not in [0, 1] or input.geo_location not in [0, 1, 2, 3, 4]:
            raise HTTPException(status_code=400, detail="Invalid categorical values")
        if input.votes_per_user > 10 or input.multiple_logins > 10:
            raise HTTPException(status_code=400, detail="Excessive vote or login count")

        # Prepare input for model
        features = np.array([[
            input.time_diff,
            input.votes_per_user,
            input.vpn_usage,
            input.multiple_logins,
            input.session_duration,
            input.geo_location
        ]])

        # Predict
        fraud_label = int(model.predict(features)[0])
        fraud_probability = float(model.predict_proba(features)[0][1])

        return {
            "fraud_label": fraud_label,
            "fraud_probability": fraud_probability
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Prediction error: {str(e)}")

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=800)