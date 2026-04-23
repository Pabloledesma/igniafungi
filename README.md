# Ignia Fungi - Platform

**Ignia Fungi** is a comprehensive platform for the management, production, and sale of gourmet and medicinal mushrooms. This system integrates an advanced **AI Agent** for sales and customer service, a rigorous **Cultivation Tracking System** (Batches), and a complete **E-commerce** solution.

## 🚀 Overview

The project is built on **Laravel 12** and utilizes **Filament 4** for the administrative backend and **Livewire 3** for the dynamic frontend. It is designed to handle the complexities of biological production (mycology) while providing a seamless, AI-assisted purchasing experience for customers.

## ✨ Key Features

### 🤖 AI Sales Agent (Gemini Integration)
A sophisticated conversational agent powered by Google Gemini, designed to guide users from inquiry to purchase.

- **Pipeline Architecture**: Requests are processed through a strict pipeline:
  - **Spam Filter**: Blocks invalid interactions.
  - **Handoff**: Detects requests for human agents.
  - **Registration**: Handles user data collection lazily.
  - **Order Intent**: Detects purchase intent and "mental cart" accumulation.
  - **Catalog & Shipping**: specialized handlers for product queries and logistics.
  - **LLM Fallback**: General conversation using Gemini 2.0.
- **Context Awareness**: Remembers user location (City/Locality) and selected products throughout the session.
- **Smart Restrictions**: Automatically enforces shipping rules (e.g., *Fresh Mushrooms* only valid for Bogotá).
- **Fuzzy Matching**: Intelligently understands typos in cities and product names.

### 🍄 Cultivation Management (Mycology)
A specialized module for tracking the lifecycle of mushroom batches.

- **Batch Tracking**: Full traceability from inoculation to harvest.
- **Lifecycle Phases**: Manages transitions between *Incubation*, *Fruiting*, etc.
- **Loss Management**: Tracks contamination and defects with detailed reason codes.
- **Yield Forecasting**: Estimates harvest dates and quantities based on strain-specific metadata.
- **Inventory Integration**: Real-time stock calculation based on active batches and projected yields.

### 🔗 Salesforce Integration (Bidirectional Sync)
Real-time, bidirectional sync between Ignia Fungi and a Salesforce org via **OAuth 2.0 Client Credentials** and **Apex REST**.

- **Push (Laravel → Salesforce)**: Every time a `Batch` or `Harvest` is created or updated, an observer dispatches a background job that upserts the record in Salesforce using the external ID `ignia_id__c`.
- **Pull (Salesforce → Laravel)**: The `salesforce:pull-batches` command (scheduled daily at 03:00) fetches computed fields from Salesforce (`sf_eficiencia_biologica`, `sf_total_cosechado_kg`, `sf_cantidad_cosechas`) and enriches local records.
- **Bulk Import**: The `salesforce:import-batches` command imports Salesforce `Lote__c` records that have no `igniaId` yet, creates local `Batch` records, and links them back automatically.
- **Platform Events (CometD)**: The `salesforce:listen` daemon subscribes to `LoteActualizado__e` via the Bayeux long-polling protocol. When Salesforce publishes a state-change event, Laravel immediately updates the affected batch — no polling needed.

### 🛒 E-commerce & Logistics
- **Dynamic Catalog**: Categorization by strains and product types (Fresh vs. Dry).
- **Shipping Zones**: Granular shipping cost calculation based on Cities and Localities.
- **Pre-orders**: Logic to allow sales against future harvest batches.
- **Coupons**: Discount system with usage tracking.

## 🛠 Tech Stack

- **Framework**: Laravel 12.x
- **Admin Panel**: Filament 4.x
- **Frontend**: Livewire 3.x, Tailwind CSS 4.x
- **Database**: MySQL / MariaDB
- **AI/LLM**: Google Gemini API
- **CRM**: Salesforce (Apex REST, Platform Events via CometD/Bayeux)
- **Testing**: PHPUnit (Feature & Unit tests)
- **Tools**: Laravel Sail (Docker environment)

## 📂 Project Structure

- `app/Services/Ai`: Contains the AI pipeline logic, handlers, and Gemini client.
- `app/Services/SalesforceService.php`: OAuth auth, Apex REST client, and sobject upserts.
- `app/Services/SalesforceCometDService.php`: Bayeux long-polling client for Platform Events.
- `app/Jobs/`: Background jobs for syncing Batches and Harvests to/from Salesforce.
- `app/Console/Commands/`: Artisan commands for bulk import, pull, and event listening.
- `app/Models`: Core entities including specialized `Batch` and `Strain` models.
- `tests/Feature`: Extensive test suite covering AI logic, cultivation flows, checkout, and Salesforce sync.

## ⚙️ Installation

1.  **Clone the repository**:
    ```bash
    git clone https://github.com/pabloledesma/igniafungi.git
    cd ignia-fungi
    ```

2.  **Start with Sail**:
    ```bash
    ./vendor/bin/sail up -d
    ```

3.  **Install Dependencies**:
    ```bash
    ./vendor/bin/sail composer install
    ./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
    ```

4.  **Environment Setup**:
    ```bash
    ./vendor/bin/sail artisan key:generate
    ./vendor/bin/sail artisan migrate --seed
    ```

5.  **AI Configuration**:
    Add your Gemini API key to `.env`:
    ```env
    GEMINI_API_KEY=your_key_here
    ```

6.  **Salesforce Configuration**:
    Add your Salesforce External Client App credentials to `.env`:
    ```env
    SALESFORCE_CLIENT_ID=your_consumer_key
    SALESFORCE_CLIENT_SECRET=your_consumer_secret
    SALESFORCE_LOGIN_URL=https://your-org.my.salesforce.com
    ```
    The integration uses the **OAuth 2.0 Client Credentials** flow (no username/password required).

## ✅ Testing

The project maintains high code quality with a comprehensive test suite.

Run all tests:
```bash
./vendor/bin/sail artisan test
```

Run specific AI tests:
```bash
./vendor/bin/sail artisan test tests/Feature/AiAgentRefinementsTest.php
```

## 📄 License

Proprietary software developed for Ignia Fungi. All rights reserved.
