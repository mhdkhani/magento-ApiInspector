# Magecraft_ApiInspector

Magecraft_ApiInspector is a Magento 2 developer utility module that scans and extracts all available REST API endpoints in your Magento instance and generates a Postman-compatible collection (JSON format) for easy testing and documentation.

---

## 🧩 Features

- Extracts all REST API routes available in Magento 2
- Converts API metadata into a ready-to-import Postman collection
- Simple CLI command to generate the collection
- Ideal for development, testing, or API documentation

---

## 🛠 Requirements

- Magento 2.3.x or later
- PHP 7.3 or later

---

## 📦 Installation

manually copy the module to your Magento `app/code/` directory.

```bash
# Example manual installation
mkdir -p app/code/Magecraft/ApiInspector
cp -r your-module-files app/code/Magecraft/ApiInspector
bin/magento module:enable Magecraft_ApiInspector
bin/magento setup:upgrade
```

## 📦 Usage
To generate the Postman collection for all available REST APIs, run the following command:
```bash
bin/magento magecraft:api:inspect rest
```
