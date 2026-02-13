Ignia Fungi - Platform
Ignia Fungi is a comprehensive platform for the management, production, and sale of gourmet and medicinal mushrooms. This system integrates an advanced AI Agent for sales and customer service, a rigorous Cultivation Tracking System (Batches), and a complete E-commerce solution.

🚀 Overview
The project is built on Laravel 12 and utilizes Filament 4 for the administrative backend and Livewire 3 for the dynamic frontend. It is designed to handle the complexities of biological production (mycology) while providing a seamless, AI-assisted purchasing experience for customers.

✨ Key Features
🤖 AI Sales Agent (Gemini Integration)
A sophisticated conversational agent powered by Google Gemini, designed to guide users from inquiry to purchase.

Pipeline Architecture: Requests are processed through a strict pipeline:
Spam Filter: Blocks invalid interactions.
Handoff: Detects requests for human agents.
Registration: Handles user data collection lazily.
Order Intent: Detects purchase intent and "mental cart" accumulation.
Catalog & Shipping: specialized handlers for product queries and logistics.
LLM Fallback: General conversation using Gemini 2.0.
Context Awareness: Remembers user location (City/Locality) and selected products throughout the session.
Smart Restrictions: Automatically enforces shipping rules (e.g., Fresh Mushrooms only valid for Bogotá).
Fuzzy Matching: Intelligently understands typos in cities and product names.
🍄 Cultivation Management (Mycology)
A specialized module for tracking the lifecycle of mushroom batches.

Batch Tracking: Full traceability from inoculation to harvest.
Lifecycle Phases: Manages transitions between Incubation, Fruiting, etc.
Loss Management: Tracks contamination and defects with detailed reason codes.
Yield Forecasting: Estimates harvest dates and quantities based on strain-specific metadata.
Inventory Integration: Real-time stock calculation based on active batches and projected yields.
🛒 E-commerce & Logistics
Dynamic Catalog: Categorization by strains and product types (Fresh vs. Dry).
Shipping Zones: Granular shipping cost calculation based on Cities and Localities.
Pre-orders: Logic to allow sales against future harvest batches.
Coupons: Discount system with usage tracking.
🛠 Tech Stack
Framework: Laravel 12.x
Admin Panel: Filament 4.x
Frontend: Livewire 3.x, Tailwind CSS 4.x
Database: MySQL / MariaDB
AI/LLM: Google Gemini API
Testing: PHPUnit (Feature & Unit tests)
Tools: Laravel Sail (Docker environment)
📂 Project Structure
app/Services/Ai: Contains the AI pipeline logic, handlers, and Gemini client.
app/Models: Core entities including specialized Batch and Strain models.
tests/Feature: Extensive test suite covering AI logic, cultivation flows, and checkout processes.
⚙️ Installation
Clone the repository:

bash
git clone https://github.com/your-repo/ignia-fungi.git
cd ignia-fungi
Start with Sail:

bash
./vendor/bin/sail up -d
Install Dependencies:

bash
./vendor/bin/sail composer install
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
Environment Setup:

bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
AI Configuration: Add your Gemini API key to .env:

env
GEMINI_API_KEY=your_key_here
✅ Testing
The project maintains high code quality with a comprehensive test suite.

Run all tests:

bash
./vendor/bin/sail artisan test
Run specific AI tests:

bash
./vendor/bin/sail artisan test tests/Feature/AiAgentRefinementsTest.php
📄 License
Proprietary software developed for Ignia Fungi. All rights reserved.