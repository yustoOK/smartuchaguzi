import numpy as np
import pandas as pd
import random
from datetime import datetime, timedelta
import os

def generate_ip():
    return f"197.{random.randint(0, 255)}.{random.randint(0, 255)}.{random.randint(0, 255)}"

def generate_geo():
    return random.choices(['TZ', 'KE', 'UG', 'RW', 'Other'], weights=[0.85, 0.05, 0.05, 0.03, 0.02])[0]

def generate_voter_id():
    year = random.randint(2021, 2024)
    number = random.randint(10000, 99999)
    return f"T/UDOM/{year}/{number}"

def generate_training_data(num_samples=50000, fraud_ratio=0.03, output_path='C:\\Users\\yusto\\Desktop\\fraud_data - pheew.csv', seed=42):
    assert num_samples > 0, "num_samples must be positive"
    assert 0 <= fraud_ratio <= 1, "fraud_ratio must be between 0 and 1"

    np.random.seed(seed)
    random.seed(seed)

    election_start = datetime(2025, 6, 25, 8, 0)
    election_end = election_start + timedelta(hours=48)

    data = np.zeros((num_samples, 6))  # 5 features + label
    extra_data = []
    ip_pool = [generate_ip() for _ in range(num_samples // 10)]

    for i in range(num_samples):
        is_fraud = random.random() < fraud_ratio
        if random.random() < 0.02:
            is_fraud = not is_fraud

        if is_fraud:
            time_diff = max(0.01, np.random.normal(2, 1.5))
            votes_per_user = max(1, np.random.poisson(5) + 1)
            session_duration = max(10, np.random.normal(45, 20))
            multiple_logins = max(1, np.random.poisson(2) + 1)
            vpn_usage = np.random.choice([0, 1], p=[0.4, 0.6])
            geo_location = random.choices(['TZ', 'Other'], weights=[0.7, 0.3])[0]
            ip_address = random.choice(ip_pool[:len(ip_pool)//5])
        else:
            time_diff = max(0.01, np.random.normal(8, 3))
            votes_per_user = max(1, np.random.poisson(3) + 1)
            session_duration = max(10, np.random.normal(100, 30))
            multiple_logins = max(1, np.random.poisson(1) + 1)
            vpn_usage = np.random.choice([0, 1], p=[0.95, 0.05])
            geo_location = generate_geo()
            ip_address = random.choice(ip_pool)

            if random.random() < 0.05:
                vpn_usage = 1
                geo_location = 'Other'
                multiple_logins = np.random.randint(2, 5)

        noise = 0.15
        time_diff = max(0.01, time_diff + random.gauss(0, time_diff * noise))
        votes_per_user = max(1, int(votes_per_user + random.gauss(0, votes_per_user * noise)))
        session_duration = max(10, session_duration + random.gauss(0, session_duration * noise))
        multiple_logins = max(1, int(multiple_logins + random.gauss(0, multiple_logins * noise)))

        data[i] = [time_diff, votes_per_user, vpn_usage, multiple_logins, session_duration, is_fraud]
        extra_data.append([generate_voter_id(), geo_location, ip_address])

    df = pd.DataFrame(data, columns=['time_diff', 'votes_per_user', 'vpn_usage', 'multiple_logins', 'session_duration', 'label'])
    extra_df = pd.DataFrame(extra_data, columns=['voter_id', 'geo_location', 'ip_address'])
    df = pd.concat([df, extra_df], axis=1)

    df['geo_location'] = df['geo_location'].map({'TZ': 0, 'KE': 1, 'UG': 2, 'RW': 3, 'Other': 4})

    numerical_cols = ['time_diff', 'votes_per_user', 'session_duration', 'multiple_logins']
    for col in numerical_cols:
        df[col] = (df[col] - df[col].mean()) / df[col].std()

    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    df.to_csv(output_path, index=False)
    return df

if __name__ == "__main__":
    print("Generating data...")
    df = generate_training_data()
    print(f"Saved to fraud_data.csv")
    print(f"Fraud ratio: {df['label'].mean():.3f}")