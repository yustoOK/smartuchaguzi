import numpy as np
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler, OneHotEncoder
from sklearn.metrics import classification_report, roc_auc_score, confusion_matrix
import tensorflow as tf
from tensorflow.keras.models import Sequential
from tensorflow.keras.layers import Dense, Dropout
from tensorflow.keras.regularizers import l2
from tensorflow.keras.callbacks import EarlyStopping
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import uvicorn
import json
import random
import string
import hashlib

# Synthetic Data Generation
def generate_ip_address():
    return '.'.join(str(random.randint(0, 255)) for _ in range(4))

def generate_device_fingerprint():
    browsers = ['Chrome', 'Firefox', 'Safari', 'Edge']
    versions = ['91.0.4472.124', '89.0', '14.1.2', '91.0.864.48']
    os = ['Windows 10', 'MacOS Big Sur', 'Ubuntu 20.04']
    return f"Mozilla/5.0 ({random.choice(os)}) {random.choice(browsers)}/{random.choice(versions)}"

def generate_synthetic_data(n_samples=10000):
    data = []
    for _ in range(n_samples):
        is_fraud = random.choice([0, 1])
        
        # Time difference (seconds): Tighter ranges for clearer separation
        time_diff = random.randint(5, 100) if is_fraud else random.randint(300, 2000)
        
        # Votes per user: Higher for fraud
        votes_per_user = random.randint(10, 30) if is_fraud else random.randint(1, 3)
        
        # VPN usage: Stronger fraud association
        vpn_usage = 1 if is_fraud and random.random() < 0.9 else 0 if not is_fraud and random.random() < 0.95 else 1
        
        # Multiple logins: Stronger fraud association
        multiple_logins = 1 if is_fraud and random.random() < 0.8 else 0 if not is_fraud and random.random() < 0.98 else 1
        
        # Session duration (seconds): Shorter for fraud
        session_duration = random.randint(10, 200) if is_fraud else random.randint(500, 4000)
        
        # Geo location: Fraud often non-Tanzania
        geo_location = random.choice([1, 2, 3, 4]) if is_fraud else 0 if random.random() < 0.9 else random.choice([1, 2, 3, 4])
        
        # Device fingerprint
        device_fingerprint = generate_device_fingerprint()
        
        # IP history: More IPs for fraud
        ip_count = random.randint(4, 6) if is_fraud else random.randint(1, 2)
        ip_history = [generate_ip_address() for _ in range(ip_count)]
        
        # Vote pattern (seconds): Shorter for fraud
        vote_pattern = random.randint(1, 30) if is_fraud else random.randint(600, 4000)
        
        # User behavior: Lower for fraud
        user_behavior = random.randint(5, 30) if is_fraud else random.randint(70, 100)
        
        data.append({
            'time_diff': time_diff,
            'votes_per_user': votes_per_user,
            'vpn_usage': vpn_usage,
            'multiple_logins': multiple_logins,
            'session_duration': session_duration,
            'geo_location': geo_location,
            'device_fingerprint': device_fingerprint,
            'ip_history': json.dumps(ip_history),
            'vote_pattern': vote_pattern,
            'user_behavior': user_behavior,
            'is_fraud': is_fraud
        })
    
    df = pd.DataFrame(data)
    
    # Save to CSV
    df.to_csv('synthetic_voting_data.csv', index=False)
    
    # Preprocess data
    numerical_features = ['time_diff', 'votes_per_user', 'session_duration', 'vote_pattern', 'user_behavior']
    categorical_features = ['geo_location']
    binary_features = ['vpn_usage', 'multiple_logins']
    
    scaler = StandardScaler()
    df[numerical_features] = scaler.fit_transform(df[numerical_features])
    
    encoder = OneHotEncoder(sparse_output=False, categories=[list(range(5))])
    geo_encoded = encoder.fit_transform(df[['geo_location']])
    geo_columns = [f'geo_{i}' for i in range(5)]
    df[geo_columns] = geo_encoded
    
    df['ip_count'] = df['ip_history'].apply(lambda x: len(json.loads(x)))
    df['device_hash'] = df['device_fingerprint'].apply(lambda x: int(hashlib.md5(x.encode()).hexdigest(), 16) % 1000)
    
    df = df.drop(columns=['geo_location', 'device_fingerprint', 'ip_history'])
    
    import pickle
    with open('scaler.pkl', 'wb') as f:
        pickle.dump(scaler, f)
    with open('encoder.pkl', 'wb') as f:
        pickle.dump(encoder, f)
    
    return df

# Generate and split data
data = generate_synthetic_data()
X = data.drop(columns=['is_fraud'])
y = data['is_fraud']
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42, stratify=y)

# Building Neural Network
model = Sequential([
    Dense(64, activation='relu', input_shape=(X_train.shape[1],), kernel_regularizer=l2(0.001)),
    Dropout(0.2),
    Dense(32, activation='relu', kernel_regularizer=l2(0.001)),
    Dropout(0.2),
    Dense(16, activation='relu', kernel_regularizer=l2(0.001)),
    Dense(1, activation='sigmoid')
])

model.compile(optimizer='adam', loss='binary_crossentropy', metrics=['accuracy'])

# Early stopping
early_stopping = EarlyStopping(monitor='val_loss', patience=5, restore_best_weights=True)

# Train model
history = model.fit(
    X_train, y_train,
    validation_split=0.2,
    epochs=50,
    batch_size=32,
    callbacks=[early_stopping],
    verbose=1
)

# Evaluate model
y_pred_proba = model.predict(X_test)
y_pred = (y_pred_proba > 0.5).astype(int)
print("Classification Report:")
print(classification_report(y_test, y_pred))
print("ROC-AUC Score:", roc_auc_score(y_test, y_pred_proba))
print("Confusion Matrix:")
print(confusion_matrix(y_test, y_pred))

# Save model
model.save('fraud_detection_model.keras')
