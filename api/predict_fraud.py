import sys
import json
import tensorflow as tf
import numpy as np

try:
    model = tf.keras.models.load_model('./neuralnet/fraud_model.keras')
    features = json.loads(sys.argv[1])
    if len(features) != 7:
        print(json.dumps({'error': 'Expected 7 features, got ' + str(len(features))}))
        sys.exit(1)
    features = np.array([features])
    prediction = model.predict(features)
    label = 1 if prediction[0][1] > 0.5 else 0
    confidence = float(prediction[0][1] if label else prediction[0][0])
    print(json.dumps({'label': label, 'confidence': confidence}))
except Exception as e:
    print(json.dumps({'error': str(e)}))
    sys.exit(1)