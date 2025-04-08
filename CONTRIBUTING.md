
# Contributing to Kazinduzi (Laravel Backend)

Thank you for considering contributing to **Kazinduzi** — a collaborative platform designed to help users contribute and explore meaningful words and their meanings in our language and culture. ❤️

This document will guide you through the steps to get started and make effective contributions to the Laravel backend.

---

## 🚀 Getting Started

1. **Fork the repository**  
   Click the "Fork" button on GitHub, then clone your fork:

   ```bash
   git clone https://github.com/Akanyaburunga/kazinduzi_backend.git
   cd kazinduzi_backend
   ```

2. **Install dependencies**  
   Make sure you have PHP, Composer, and MySQL/PostgreSQL installed.

   ```bash
   composer install
   ```

3. **Set up the environment**  
   Copy the `.env.example` file and adjust the necessary configurations:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Then, configure your database and run:

   ```bash
   php artisan migrate --seed
   ```

4. **Start the development server**

   ```bash
   php artisan serve
   ```

---

## 🧪 Running Tests

Run the test suite using:

```bash
php artisan test
```

Please write tests for any new features or bug fixes you introduce.

---

## 🛠 Contributing Guidelines

- Create a new branch for your work:
  ```bash
  git checkout -b feature/my-new-feature
  ```

- Keep your code clean and readable.
- Follow Laravel’s conventions and [PSR-12](https://www.php-fig.org/psr/psr-12/) standards.
- Include helpful commit messages:
  ```
  git commit -m "Add referral system to registration"
  ```

- Submit a **pull request (PR)** from your feature branch to `master`.

---

## ✅ Pull Request Checklist

- [ ] Your code works as expected.
- [ ] Tests pass (or are included if needed).
- [ ] Follows Laravel code style.
- [ ] No unnecessary files (e.g. `.DS_Store`, `node_modules`, IDE configs).
- [ ] You’ve documented any config/env changes.

---

## 🙋 Need Help?

If you’re stuck, open an issue or start a discussion. We're happy to assist and collaborate!

---

## 📜 License

By contributing, you agree that your contributions will be licensed under the [MIT license](https://github.com/Akanyaburunga/kazinduzi_backend/blob/master/LICENSE.md).
