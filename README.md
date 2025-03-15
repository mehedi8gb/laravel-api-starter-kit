# Software Requirements Specification (SRS)

## 1. Project Overview
**Project Name:** Simple Invoice Generator  
**Objective:** A 1-2 step invoice generation tool allowing users to create and share invoices easily. 
**Tech Stack:** 
- Frontend: Vue.js (Nuxt.js)
- Backend: Laravel API
**Target Completion:** 2 Days

## 2. Core Features
### 2.1 Authentication
- User Registration & Login (JWT-Based)
- Forgot Password (Email Verification)

### 2.2 Company & Client Management
- User can add multiple companies.
- Each company can have multiple clients.
- CRUD operations for companies & clients.

### 2.3 Product Management
- Users can add products with:
  - Name
  - Description (optional)
  - Price

### 2.4 Invoice Generation
- Users can create invoices with:
  - Company Details
  - Client Details
  - Selected Products (with quantity & price calculation)
  - Auto-generated Invoice Number (Format: INV-00001)
  - Invoice Date
  - Total Amount Calculation
- Invoices are accessible via unique links:
  - `invoice.com/{company_slug}/invoice/{invoice_id}`
- Invoice preview and PDF download.

### 2.5 Invoice Sharing
- Users can share the invoice link with clients.
- Email invoice to clients (Optional for v2).

## 3. API Endpoints (Backend - Laravel)
### Authentication
- `POST /api/register` (Register user)
- `POST /api/login` (Login user)
- `POST /api/logout` (Logout user)

### Company Management
- `POST /api/companies` (Create company)
- `GET /api/companies` (List companies)
- `PUT /api/companies/{id}` (Update company)
- `DELETE /api/companies/{id}` (Delete company)

### Client Management
- `POST /api/clients` (Create client)
- `GET /api/clients` (List clients)
- `PUT /api/clients/{id}` (Update client)
- `DELETE /api/clients/{id}` (Delete client)

### Product Management
- `POST /api/products` (Create product)
- `GET /api/products` (List products)
- `PUT /api/products/{id}` (Update product)
- `DELETE /api/products/{id}` (Delete product)

### Invoice Management
- `POST /api/invoices` (Create invoice)
- `GET /api/invoices` (List invoices)
- `GET /api/invoices/{id}` (View invoice details)
- `DELETE /api/invoices/{id}` (Delete invoice)

## 4. Frontend (Nuxt.js)
- **Pages:** 
  - Dashboard (List invoices, companies, clients, products)
  - Invoice Creation Page (Form to generate invoices)
  - Invoice View Page (Public invoice display & PDF download)
  - Login/Register Pages
- **State Management:** Pinia/Vuex
- **Styling:** Tailwind CSS

## 5. Database Schema (MySQL/MariaDB)
- **Users:** id, name, email, password, created_at
- **Companies:** id, user_id, name, address, created_at
- **Clients:** id, company_id, name, email, phone, created_at
- **Products:** id, company_id, name, description, price, created_at
- **Invoices:** id, company_id, client_id, invoice_number, invoice_data (JSON), created_at

## 6. Deployment Plan
- Backend: VPS (Laravel API)
- Frontend: Vercel/Netlify (Nuxt.js)
- Database: MySQL/MariaDB (Host on VPS)

## 7. Timeline
- **Day 1:** Backend API development & Database setup
- **Day 2:** Connect frontend with API, testing, and deployment

## 8. Future Enhancements (After MVP)
- PDF invoice download
- Email invoice to clients
- Multi-currency support
- Payment integration

## 9. GitHub Project Setup
### Project Structure
```
/invoice-mvp
  ├── frontend  # Nuxt.js (Vue.js) Frontend
  ├── backend   # Laravel API Backend
  ├── README.md # Documentation
```

### Setup Instructions
1. **Clone the repository:**
   ```sh
   git clone https://github.com/your-repo/invoice-mvp.git
   cd invoice-mvp
   ```

2. **Setup Backend (Laravel API):**
   ```sh
   cd backend
   cp .env.example .env
   composer install
   php artisan key:generate
   php artisan migrate --seed
   php artisan serve
   ```

3. **Setup Frontend (Nuxt.js):**
   ```sh
   cd ../frontend
   npm install
   npm run dev
   ```

4. **Run the Application:**
   - Backend: `http://127.0.0.1:8000`
   - Frontend: `http://localhost:3000`

## 10. GitHub Branching Policy
### Branch Structure
- `main` - Stable production-ready branch
- `develop` - Development integration branch
- `feature/{feature-name}` - Feature-specific branches
- `hotfix/{issue-name}` - Critical bug fixes

### Git Workflow
1. **Checkout develop branch:**
   ```sh
   git checkout develop
   git pull origin develop
   ```
2. **Create a feature branch:**
   ```sh
   git checkout -b feature/{feature-name}
   ```
3. **Commit changes:**
   ```sh
   git add .
   git commit -m "Implemented {feature-name}"
   ```
4. **Push to remote:**
   ```sh
   git push origin feature/{feature-name}
   ```
5. **Create a Pull Request (PR) to develop branch:**
   - Open GitHub and create a PR from `feature/{feature-name}` to `develop`
6. **After review & merge, delete the feature branch:**
   ```sh
   git branch -d feature/{feature-name}
   git push origin --delete feature/{feature-name}
   ```

### Hotfix Process
1. **Checkout main branch:**
   ```sh
   git checkout main
   git pull origin main
   ```
2. **Create a hotfix branch:**
   ```sh
   git checkout -b hotfix/{issue-name}
   ```
3. **Apply fix, commit & push:**
   ```sh
   git add .
   git commit -m "Fixed {issue-name}"
   git push origin hotfix/{issue-name}
   ```
4. **Create a PR to `main` and `develop` branches, merge and delete branch.**

**Note:** Focus on **functionality over perfection** to launch within 2 days.

