# Gunpla Hangar

A self-hosted, comprehensive asset management system designed for gunpla builders or other model kit builders by being able to track physical inventory, plan backlogs, monitor build progress, and manage paint supplies.

## Key Stuffs

### Kit Management
* **Inventory Tracking**: Comprehensive CRUD operations for your collection, including brand, status, price, and purchase dates.
* **Backlog Planning**: Organize your upcoming projects and prioritize your build queue.
* **Wishlist**: Maintain a dedicated list of desired kits with priority levels and links.

###  Build Progress
* **Task Lists**: Break down complex builds into granular, manageable tasks.
* **Transaction Logs**: Document every step of your build with notes and photo uploads.
* **Status Tracking**: Real-time progress updates for every active project.
* **Blueprint Editor**: Sketch, annotate, and visually plan builds on an interactive digital canvas.

### Paint & Supplies
* **Paint Inventory**: Track your stock levels, paint types (Lacquer, Acrylic, etc.), and brands.
* **Mixing Recipes**: Store and manage custom paint ratios with visual references.
* **Paint Wishlist**: Track specific colors or supplies needed for future projects.

## Tech Stack
* **Backend**: PHP with a custom dynamic router.
* **Database**: MySQL / MariaDB with structured relational schema.
* **Frontend**: Tailwind CSS and Vanilla JavaScript.
* **Infrastructure**: Nginx and Docker Compose.

## 📦 Installation & Setup

### Prerequisites
* [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed on your machine.

### Deployment
1.  Clone this repository to your local machine.
2.  Navigate to the project folder in your terminal.
3.  Run the following command:
    ```bash
    docker compose up -d
    ```
4.  The application will be available at `http://localhost`.
