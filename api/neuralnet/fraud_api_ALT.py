# fraud_api.py(Alternative API for fraud detection after the one implemented in api/predict_fraud.py)

#This is a FastAPI application that serves a machine learning model for fraud detection.
# It accepts a POST request with user and voting information, processes the data, and returns a prediction of whether the vote is fraudulent or not.

# The model is loaded from a specified path and the input data is validated before making predictions(Hence we need to ensure it actually exists to avoid exceptions).

# The application handles exceptions and returns appropriate HTTP status codes and messages in case of errors.

from fastapi import FastAPI, HTTPException, Request
from pydantic import BaseModel
import tensorflow as tf
import numpy as np

app = FastAPI()
model = tf.keras.models.load_model('fraud_model.keras')

class FraudRequest(BaseModel):
    user_id: int
    voter_id: str
    vote_timestamp: str
    time_diff: float = 0
    vote_frequency: float = 0
    vpn_usage: bool = False
    multiple_logins: int = 0
    votes_per_user: int = 0
    avg_time_between_votes: float = 0

@app.post("/check_fraud")
async def check_fraud(request: FraudRequest):
    try:
        voter_id_numeric = ''.join(filter(str.isdigit, request.voter_id))
        voter_id = float(voter_id_numeric) / 1_000_000

        features = [
            request.time_diff,
            request.votes_per_user,
            voter_id,
            request.avg_time_between_votes,
            request.vote_frequency,
            int(request.vpn_usage),
            request.multiple_logins
        ]

        if len(features) != 7:
            raise HTTPException(status_code=400, detail="Expected 7 features")

        prediction = model.predict(np.array([features]))
        label = 1 if prediction[0][1] > 0.5 else 0
        confidence = float(prediction[0][1] if label else prediction[0][0])

        return {
            "label": label,
            "confidence": confidence
        }

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
