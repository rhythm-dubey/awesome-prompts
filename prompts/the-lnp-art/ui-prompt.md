Create a modern minimal furniture catalogue website using Next.js (App Router) and Tailwind CSS.

Design inspiration:
- orangetree.in
- crestviewcollection.com
- htddirect.com

Requirements:

1. Authentication:
- Google Sign-In using Firebase Auth
- Only allow access to users with emails from a predefined whitelist
- Redirect unauthorized users to login page

2. Pages:
- Home Page:
  - Hero section with large furniture imagery
  - Featured products grid
  - Minimal typography, beige/neutral color palette

- About Page:
  - Brand story
  - Image-based sections

- Product Catalogue Page:
  - Grid layout
  - Product cards (image, name, price)
  - Basic category filter

- Product Detail Page:
  - Image gallery
  - Product description
  - Price and category

- Contact Page:
  - Contact form (name, email, message)
  - Store in Firebase Firestore

3. Admin Panel (simple):
- Login protected
- View users list
- CRUD products (name, images, price, category, description)
- Upload images to Firebase Storage

4. Tech:
- Next.js App Router
- Tailwind CSS
- Firebase (Auth, Firestore, Storage)

5. UI Style:
- Clean, minimal
- White + beige + wood tones
- Focus on product images
- Responsive design

Generate clean reusable components and folder structure.