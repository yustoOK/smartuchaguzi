import numpy as np
import tensorflow as tf
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
import pickle
import os

# Set random seed for reproducibility
np.random.seed(42)
tf.random.set_seed(42)

# Generate synthetic dataset (mimics generate_fraud_data_udom_v4.py)
def generate_synthetic_data(n_samples=50000, fraud_ratio=0.03):
    X = []
    y = []
    for _ in range(n_samples):
        is_fraud = np.random.random() < fraud_ratio
        if is_fraud:
            X.append([
                max(0.01, np.random.normal(2, 1.5)),  # time_diff
                max(1, np.random.poisson(5) + 1),      # votes_per_user
                np.random.choice([0, 1], p=[0.4, 0.6]), # vpn_usage
                max(1, np.random.poisson(2) + 1),      # multiple_logins
                max(10, np.random.normal(45, 20)),     # session_duration
                np.random.choice([0, 4], p=[0.7, 0.3]) # geo_location
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
    return np.array(X), np.array(y)

# Generate data
X, y = generate_synthetic_data()

# Split data
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42, stratify=y)

# Scale features
scaler = StandardScaler()
X_train_scaled = scaler.fit_transform(X_train)
X_test_scaled = scaler.transform(X_test)

# Save scaler
with open('scaler.pkl', 'wb') as f:
    pickle.dump(scaler, f)

# Build NN model
model = tf.keras.Sequential([
    tf.keras.layers.Dense(64, activation='relu', input_shape=(6,)),
    tf.keras.layers.Dropout(0.3),
    tf.keras.layers.Dense(32, activation='relu'),
    tf.keras.layers.Dropout(0.3),
    tf.keras.layers.Dense(16, activation='relu'),
    tf.keras.layers.Dense(1, activation='sigmoid')
])

# Compile model
model.compile(optimizer='adam', loss='binary_crossentropy', metrics=['accuracy'])

# Train model
history = model.fit(
    X_train_scaled, y_train,
    validation_data=(X_test_scaled, y_test),
    epochs=20,
    batch_size=32,
    class_weight={0: 1.0, 1: 10.0},  # Handle class imbalance
    verbose=1
)

# Save model
model.save('fraud_model.h5')

print("Model training completed and saved as 'fraud_model.h5'.")