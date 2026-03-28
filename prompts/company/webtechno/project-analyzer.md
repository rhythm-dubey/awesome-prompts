You are a senior software architect and codebase analyst.

Your task is to deeply analyze the entire project folder structure and generate complete technical documentation.

## 📁 Step 1: Project Structure Analysis

* Recursively scan all folders and files
* Generate a clean tree structure of the project
* Identify key directories such as:

  * src/
  * routes/
  * controllers/
  * models/
  * services/
  * pages / views
  * middleware
  * config files

## 🧠 Step 2: Functional Analysis

* Identify all major features and functionalities of the application
* Explain what the project does (high-level summary)
* Break down each module and its purpose

## 🌐 Step 3: Routes Analysis

* List all API routes or app routes
* Mention:

  * Route path
  * HTTP method (GET, POST, etc.)
  * संबंधित controller/function
  * Purpose of each route

## 🎮 Step 4: Controllers

* List all controllers
* For each controller:

  * Functions inside it
  * What each function does
  * Input and output

## 🗄️ Step 5: Models / Database

* Identify all models/entities
* List:

  * Fields
  * Relationships (if any)
  * ORM/Database type (MongoDB, SQL, etc.)

## 🖥️ Step 6: Pages / Frontend

* List all pages or UI components
* Describe:

  * Purpose of each page
  * Navigation flow (if possible)

## ⚙️ Step 7: Dependencies & Tech Stack

* Detect:

  * Framework (React, Next.js, Express, etc.)
  * Libraries used
  * Package.json / requirements.txt analysis

## 🔄 Step 8: Data Flow

* Explain how data flows:

  * Request → Route → Controller → Model → Response

## ⚠️ Step 9: Issues & Code Quality

* Detect:

  * Code duplication
  * Bad practices
  * Missing validations
  * Security concerns

## 🚀 Step 10: Upgrade Suggestions

* Suggest:

  * Better architecture (MVC, Clean Architecture, etc.)
  * Performance improvements
  * Scalability improvements
  * Migration suggestions (e.g., to Next.js, Microservices, etc.)

## 📄 Step 11: Final Output Format

Generate output in this format:

1. Project Overview
2. Folder Structure
3. Features List
4. Routes Table
5. Controllers Breakdown
6. Models Description
7. Pages / UI Flow
8. Tech Stack
9. Issues
10. Upgrade Plan

Make the documentation clean, structured, and developer-friendly.
Use tables wherever possible.

Analyze this repository: <repo link>
I will provide files step by step. Maintain context.
Work in chunks and keep memory of previous files.

# Add this line at top
Think like a tech lead preparing documentation for a production system migration.

# upgrade-focus Prompt
After analysis, suggest a complete migration plan to modern stack (e.g., MERN, Next.js, or microservices), including folder restructuring.