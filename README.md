# 🚫 NSFW Detector PHP

A lightweight, single-file PHP API for detecting NSFW (Not Safe For Work) content from image URLs.

> Just upload `index.php` to your server and start analyzing images instantly.

---

## ⚡ Features

* 🧩 Single-file deployment (no dependencies)
* 🔗 Analyze images via direct URL
* 🚫 Detect NSFW categories (Porn, Sexy, Hentai, etc.)
* 📊 Confidence scoring & risk levels
* ⚙️ Configurable sensitivity
* ⚡ Fast and easy integration

---

## 🚀 Quick Start

Upload the `index.php` file to your server or hosting.

Then send a request:

```bash
GET /index.php/?url=https://example.com/image.jpg
```

---

## 📥 Example Response

```json
{
  "success": true,
  "isSafe": false,
  "confidence": 97,
  "recommendation": "Content detected as Sexy. Consider filtering or reviewing.",
  "fileInfo": {
    "width": 960,
    "height": 1280,
    "size": 150561
  },
  "predictions": [
    {
      "category": "Sexy",
      "probability": 97.47,
      "risk": "High"
    }
  ]
}
```

---

## 🧠 How It Works

1. Receives an image URL
2. Sends it to an external NSFW detection API
3. Processes classification results
4. Returns structured JSON response

---

## ⚙️ Parameters

| Parameter | Type   | Description          |
| --------- | ------ | -------------------- |
| url       | string | Image URL to analyze |

---

## 📌 Categories Detected

* Porn
* Sexy
* Neutral
* Hentai
* Drawing

---

## 🛠 Requirements

* PHP 7.4+
* cURL enabled

---

## 💡 Use Cases

* Content moderation systems
* Upload filtering
* Social media apps
* Bots & automation tools

---

## 📄 License

MIT License
