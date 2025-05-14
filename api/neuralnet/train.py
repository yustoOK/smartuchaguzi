import pandas as pd
import numpy as np
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from sklearn.utils import class_weight
import tensorflow as tf
from tensorflow.keras import layers, models
import pickle
import os

# Set random seed for reproducibility
np.random.seed(42)
tf.random.set_seed(42)

# Load dataset
data_path = "C:\\Users\\yusto\\Desktop\\fraud_data - pheew.csv"
try:
    df = pd.read_csv(data_path)
except FileNotFoundError:
    raise FileNotFoundError(f"Dataset not found at {data_path}")

# Select features and target
features = ['time_diff', 'votes_per_user', 'vpn_usage', 'multiple_logins', 'session_duration', 'geo_location']
target = 'label'

# Validate dataset
if not all(col in df.columns for col in features + [target]):
    raise ValueError("Dataset missing required columns")

# Handle missing values
df = df[features + [target]].dropna()

# Features and target
X = df[features].values
y = df[target].values

# Split data
X_train, X_val, y_train, y_val = train_test_split(X, y, test_size=0.2, random_state=42, stratify=y)

# Scale numerical features
scaler = StandardScaler()
numerical_indices = [0, 1, 4]  # time_diff, votes_per_user, session_duration
X_train_scaled = X_train.copy()
X_val_scaled = X_val.copy()
X_train_scaled[:, numerical_indices] = scaler.fit_transform(X_train[:, numerical_indices])
X_val_scaled[:, numerical_indices] = scaler.transform(X_val[:, numerical_indices])

# Save scaler
with open('scaler.pkl', 'wb') as f:
    pickle.dump(scaler, f)

# Compute class weights to handle imbalance (~3% fraud)
class_weights = class_weight.compute_class_weight('balanced', classes=np.unique(y_train), y=y_train)
class_weight_dict = {0: class_weights[0], 1: class_weights[1]}

# Build NN model
model = models.Sequential([
    layers.Input(shape=(len(features),)),
    layers.Dense(64, activation='relu'),
    layers.Dropout(0.3),
    layers.Dense(32, activation='relu'),
    layers.Dropout(0.3),
    layers.Dense(16, activation='relu'),
    layers.Dense(1, activation='sigmoid')
])

# Compile model
model.compile(optimizer='adam', loss='binary_crossentropy', metrics=['accuracy'])

# Train model
history = model.fit(
    X_train_scaled, y_train,
    validation_data=(X_val_scaled, y_val),
    epochs=20,
    batch_size=32,
    class_weight=class_weight_dict,
    verbose=1
)

# Save model
model.save('fraud_model.h5')

print("Training complete. Model and scaler saved.")