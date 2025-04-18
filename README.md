# Kazinduzi: a modern, digital home for the Kirundi language

**Kazinduzi** is a community-driven platform for documenting and preserving the **Kirundi** language — powered by open-source technology, cultural pride, and collective knowledge.

> 📄 [Soma mu Kirundi](README_KIRUNDI.md)

It allows users to collaboratively contribute Kirundi words, meanings, and linguistic context. It promotes learning, preserves heritage, and encourages engagement through gamification, mobile access, and community moderation.

---

## 🌍 Explore the Kazinduzi Platform

The official Kazinduzi platform is live at:

👉 **[https://www.kazinduzi.org](https://www.kazinduzi.org)**

This is not just a demo — it is the **core destination** for discovering, contributing, and preserving **Kirundi** words, expressions, and meanings. Every feature you see in the repository is actively powering the live platform:

- 📚 Browse a growing database of Kirundi words
- ✍️ Add your own meanings and suggestions
- 🗳 Vote on the best definitions and boost quality
- 🏅 Climb the leaderboard and earn reputation
- 🤝 Invite others through referral links
- 🛡 Help moderate content as a trusted contributor

Whether you're a native speaker, language learner, linguist, teacher, or developer, **Kazinduzi.org** is built for you. Join us in building a public, digital resource for Kirundi — one word at a time.

> “Uwutazi ikirundi akirundararamwo.”

---

## ✨ Features

- 📚 Submit and search **Kirundi words** with rich meanings
- 🔄 Each word can have **multiple meanings** by different users
- 🗳 Voting system for meanings (StackOverflow-style)
- 🏆 **Reputation system** to reward contributors
- 🎯 **Referral program** with bonus points for verified invites
- 🔍 Search with **autocomplete** and smart filtering
- 🌐 API endpoints for all core features
- 🛡 **Community moderation**: trusted users can suspend content and users
- ⚡ Featured, Trending, and Recent words on homepage
- 🌗 Dark mode toggle
- 💬 Social sharing buttons for referral links
- 📊 Leaderboard with time-based filters (Today, This Week, All Time)

---

## 🏗 Tech Stack

- **Backend**: Laravel 10.x (PHP 8+)
- **Frontend**: Blade + Bootstrap 5
- **Database**: MySQL / MariaDB
- **Mobile Client**: Android (Java, RealmDB, WorkManager)
- **Sync Mechanism**: Custom API sync for words, meanings, votes, and users

---

## 🚀 Getting Started

### 1. Clone the Repository

```bash
git clone https://github.com/Akanyaburunga/kazinduzi_backend.git
cd kazinduzi_backend
```

### 2. Install Dependencies

```bash
composer install
npm install && npm run dev
```

### 3. Environment Setup

Create your `.env` file:

```bash
cp .env.example .env
```

Make sure you give values to these keys in `.env`:

```env
APP_NAME=
DEFAULT_ADMIN_EMAIL=
DEFAULT_ADMIN_NAME=
DEFAULT_ADMIN_PASSWORD=
TURNSTILE_SITEKEY=
TURNSTILE_SECRET=
MODERATION_REPUTATION_THRESHOLD=
```

Then run:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

✅ This will create a default admin user and seed curated Kirundi words from a pre-compiled JSON file.

---

## 📱 Android Client

A fully functional Android app (Java + Realm) is under development. It supports:
- Offline viewing and searching
- Background syncing of new and updated words/meanings
- On This Day + mini-leaderboard on homepage
- Dark mode and search filters

> Source to be published separately soon.

---

## 🔒 Authentication

- Email verification required in **production**
- Optional 6-digit verification code (instead of links)
- Users must be verified to vote, contribute, or invite

---

## 🧠 Reputation System

Users gain reputation points by:
- Adding new words and meanings
- Getting upvotes
- Inviting verified users via referral links

Users with high reputation (configurable via `.env`) gain **moderation privileges**:
- Ban users
- Suspend/unsuspend content (words and meanings)

All moderation actions are logged and reversible.

---

## 🤝 Contributing

We welcome your help! Whether you're a:
- Developer (Laravel, Android)
- Translator or Kirundi language expert
- Teacher, linguist, or cultural ambassador

See [CONTRIBUTING.md](CONTRIBUTING.md) to get started.

You can also join our community on **[Discord](https://discord.gg/7ZE6BQkW)** or **[WhatsApp](https://chat.whatsapp.com/EMbBX8nuVr42Tyovkcy3gP)**.

---

## 📚 Roadmap

See [ROADMAP.md](ROADMAP.md) for upcoming features and goals.

Some planned features:
- In-app notifications
- Profile pages and badges
- Cultural examples and word categories
- Speech input for pronunciation
- Word games and quizzes

---

## 🧪 Testing

Run tests (if applicable):

```bash
php artisan test
```

---

## 📄 License

This project is open-sourced under the [MIT License](https://github.com/Akanyaburunga/kazinduzi_backend/blob/master/LICENSE.md).

---

## ❤️ Credits

Kazinduzi is created and maintained by [Akanyaburunga](https://github.com/Akanyaburunga), with love for the Kirundi language and Burundian heritage.

## 🌱 About Akanyaburunga

**Akanyaburunga** is a nonprofit organization registered in **Burundi** with a mission to **revive, preserve, and promote Burundian cultural heritage**. We believe that language is a living vessel of identity, wisdom, and belonging — and that by protecting it, we reconnect with who we are.

**Kazinduzi** is one of Akanyaburunga’s flagship initiatives, designed to:

- 🌍 Preserve and celebrate the **Kirundi language**
- 🧠 Make local knowledge **freely accessible**
- 🤝 Empower communities to contribute and shape their own cultural resources
- 💡 Harness technology for language revival and education

> Through Kazinduzi, Akanyaburunga envisions a generation of Burundians who are not only fluent in Kirundi but also proud of the cultural legacy it carries.

📢 Learn more about Akanyaburunga and our initiatives at  
👉 [https://kazinduzi.org](https://kazinduzi.org) *(more resources coming soon)*.

Contributors are welcome and celebrated!

> “The death of a language is the death of the soul of a people.” — Ngũgĩ wa Thiong'o, Kenyan writer and language advocate
> 
> “If you talk to a man in a language he understands, that goes to his head. If you talk to him in his language, that goes to his heart.” — Nelson Mandela
