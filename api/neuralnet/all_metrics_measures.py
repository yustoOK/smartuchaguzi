import numpy as np
import pandas as pd
import matplotlib.pyplot as plt
import seaborn as sns
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler, OneHotEncoder
from sklearn.metrics import (
    accuracy_score, precision_score, recall_score, f1_score,
    roc_auc_score, average_precision_score, confusion_matrix,
    roc_curve, precision_recall_curve
)
from sklearn.inspection import permutation_importance
import tensorflow as tf
import pickle
import json
import hashlib
import os

# Set random seed for reproducibility
np.random.seed(42)
tf.random.set_seed(42)

# Create output directory for plots
if not os.path.exists('plots'):
    os.makedirs('plots')

# Load dataset
try:
    df = pd.read_csv('synthetic_voting_data.csv')
except FileNotFoundError:
    print("Error: synthetic_voting_data.csv not found.")
    exit(1)

# Dataset Summary
print("\n=== Dataset Summary ===")
print("Shape:", df.shape)
print("\nClass Distribution:")
print(df['is_fraud'].value_counts(normalize=True))
print("\nDescriptive Statistics:")
print(df.describe())

# Correlation Analysis (before preprocessing)
numerical_cols = ['time_diff', 'votes_per_user', 'session_duration', 'vote_pattern', 'user_behavior']
correlation_matrix = df[numerical_cols].corr(method='pearson')
plt.figure(figsize=(10, 8))
sns.heatmap(correlation_matrix, annot=True, cmap='coolwarm', vmin=-1, vmax=1, center=0)
plt.title('Pearson Correlation Matrix')
plt.tight_layout()
plt.savefig('plots/correlation_matrix.png')
plt.close()
print("\nCorrelation matrix saved as 'plots/correlation_matrix.png'")

# Preprocess data
numerical_features = ['time_diff', 'votes_per_user', 'session_duration', 'vote_pattern', 'user_behavior']
categorical_features = ['geo_location']
binary_features = ['vpn_usage', 'multiple_logins']

# Load scaler and encoder
try:
    with open('scaler.pkl', 'rb') as f:
        scaler = pickle.load(f)
    with open('encoder.pkl', 'rb') as f:
        encoder = pickle.load(f)
except FileNotFoundError:
    print("Error: scaler.pkl or encoder.pkl not found.")
    exit(1)

# Apply preprocessing
df_processed = df.copy()
df_processed[numerical_features] = scaler.transform(df[numerical_features])
geo_encoded = encoder.transform(df[['geo_location']])
geo_columns = [f'geo_{i}' for i in range(5)]
df_processed[geo_columns] = geo_encoded
df_processed['ip_count'] = df_processed['ip_history'].apply(lambda x: len(json.loads(x)))
df_processed['device_hash'] = df_processed['device_fingerprint'].apply(
    lambda x: int(hashlib.md5(x.encode()).hexdigest(), 16) % 1000
)
df_processed = df_processed.drop(columns=['geo_location', 'device_fingerprint', 'ip_history'])

# Split data
X = df_processed.drop(columns=['is_fraud'])
y = df_processed['is_fraud']
X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42, stratify=y
)

# Load model
try:
    model = tf.keras.models.load_model('fraud_detection_model.keras')
except FileNotFoundError:
    print("Error: fraud_detection_model.keras not found.")
    exit(1)

# Predictions
y_pred_proba = model.predict(X_test, verbose=0)
y_pred = (y_pred_proba > 0.5).astype(int).flatten()

# Performance Metrics
accuracy = accuracy_score(y_test, y_pred)
precision = precision_score(y_test, y_pred)
recall = recall_score(y_test, y_pred)
f1 = f1_score(y_test, y_pred)
roc_auc = roc_auc_score(y_test, y_pred_proba)
pr_auc = average_precision_score(y_test, y_pred_proba)
cm = confusion_matrix(y_test, y_pred)
tn, fp, fn, tp = cm.ravel()
specificity = tn / (tn + fp) if (tn + fp) > 0 else 0

print("\n=== Performance Metrics ===")
print(f"Accuracy: {accuracy:.4f}")
print(f"Precision: {precision:.4f}")
print(f"Recall: {recall:.4f}")
print(f"F1-Score: {f1:.4f}")
print(f"Specificity: {specificity:.4f}")
print(f"ROC-AUC: {roc_auc:.4f}")
print(f"PR-AUC: {pr_auc:.4f}")
print("\nConfusion Matrix (Raw):")
print(cm)
print(f"True Negatives: {tn}, False Positives: {fp}")
print(f"False Negatives: {fn}, True Positives: {tp}")

# Normalized Confusion Matrix
cm_norm = cm.astype('float') / cm.sum(axis=1)[:, np.newaxis]
plt.figure(figsize=(8, 6))
sns.heatmap(cm_norm, annot=True, cmap='Blues', fmt='.2f',
            xticklabels=['Non-Fraud', 'Fraud'], yticklabels=['Non-Fraud', 'Fraud'])
plt.title('Normalized Confusion Matrix')
plt.xlabel('Predicted')
plt.ylabel('Actual')
plt.tight_layout()
plt.savefig('plots/confusion_matrix.png')
plt.close()
print("\nConfusion matrix plot saved as 'plots/confusion_matrix.png'")

# ROC Curve
fpr, tpr, _ = roc_curve(y_test, y_pred_proba)
plt.figure(figsize=(8, 6))
plt.plot(fpr, tpr, label=f'ROC Curve (AUC = {roc_auc:.4f})')
plt.plot([0, 1], [0, 1], 'k--')
plt.xlabel('False Positive Rate')
plt.ylabel('True Positive Rate')
plt.title('ROC Curve')
plt.legend(loc='best')
plt.tight_layout()
plt.savefig('plots/roc_curve.png')
plt.close()
print("ROC curve saved as 'plots/roc_curve.png'")

# Precision-Recall Curve
precision_vals, recall_vals, _ = precision_recall_curve(y_test, y_pred_proba)
plt.figure(figsize=(8, 6))
plt.plot(recall_vals, precision_vals, label=f'PR Curve (AUC = {pr_auc:.4f})')
plt.xlabel('Recall')
plt.ylabel('Precision')
plt.title('Precision-Recall Curve')
plt.legend(loc='best')
plt.tight_layout()
plt.savefig('plots/pr_curve.png')
plt.close()
print("Precision-Recall curve saved as 'plots/pr_curve.png'")

# Feature Importance (Permutation Importance)
model_np = lambda x: model.predict(x, verbose=0) > 0.5
result = permutation_importance(model_np, X_test, y_test, n_repeats=10, random_state=42)
# Access the correct scorer key (usually 'score') in the result dictionary
scorer_key = list(result.keys())[0]
feature_importance = pd.DataFrame({
    'Feature': X_test.columns,
    'Importance': result[scorer_key].importances_mean,
    'Std': result[scorer_key].importances_std
}).sort_values(by='Importance', ascending=False)

print("\n=== Feature Importance ===")
print(feature_importance[['Feature', 'Importance']].to_string(index=False))

plt.figure(figsize=(10, 6))
sns.barplot(x='Importance', y='Feature', data=feature_importance, xerr=feature_importance['Std'])
plt.title('Feature Importance (Permutation)')
plt.tight_layout()
plt.savefig('plots/feature_importance.png')
plt.close()
print("Feature importance plot saved as 'plots/feature_importance.png'")

# Save Metrics to File
metrics = {
    'Accuracy': accuracy,
    'Precision': precision,
    'Recall': recall,
    'F1-Score': f1,
    'Specificity': specificity,
    'ROC-AUC': roc_auc,
    'PR-AUC': pr_auc,
    'Confusion Matrix': cm.tolist(),
    'True Negatives': int(tn),
    'False Positives': int(fp),
    'False Negatives': int(fn),
    'True Positives': int(tp)
}
with open('metrics.json', 'w') as f:
    json.dump(metrics, f, indent=4)
print("\nMetrics saved to 'metrics.json'")