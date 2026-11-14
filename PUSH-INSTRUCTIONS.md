# Push this repo to GitHub

You have `cro-intelligence.bundle` — a single file containing the whole git
repository (one commit, branch `main`, 182 files, no secrets).

A bundle is not a zip: it carries the real commit history, so the repo you push
is identical to the one that was tested.

---

## 1. Create the empty GitHub repo

1. Go to <https://github.com/new>.
2. **Repository name:** `cro-intelligence`
3. **Visibility:** Private (recommended — you can flip it later).
4. **Do NOT** tick "Add a README", "Add .gitignore" or "Choose a license".
   The repo must be **completely empty**, or the push in step 3 is rejected.
5. Click **Create repository**. Leave the page open — you need the URL.

---

## 2. Clone the bundle onto your machine

Download `cro-intelligence.bundle`, then in a terminal:

```bash
git clone cro-intelligence.bundle cro-intelligence
cd cro-intelligence
```

**Check:** `git log --oneline` shows exactly one commit:

```
a5b046e CRO Intelligence: production-ready Laravel 12 app for EthioDeploy (MySQL, env-driven config)
```

`git status` should say `nothing to commit, working tree clean`.

---

## 3. Point it at your new repo and push

Replace `<you>` with your GitHub username:

```bash
git remote remove origin
git remote add origin https://github.com/<you>/cro-intelligence.git
git branch -M main
git push -u origin main
```

**Check:** refresh the GitHub page — 182 files, one commit, branch `main`.

---

## 4. Confirm nothing secret went up

On GitHub, look at the file list at the repo root. You should see
`.env.example` but **not** `.env`. There should be no `vendor/` or
`node_modules/` folder.

To double-check from your terminal:

```bash
git ls-files | grep -E '(^|/)\.env$|vendor/|node_modules/'
```

No output = clean.

---

## If the push fails

| Error | Cause | Fix |
|---|---|---|
| `! [rejected] main -> main (fetch first)` | The GitHub repo is not empty (you ticked "Add a README") | Delete the repo and recreate it empty, or run `git push -u origin main --force` |
| `remote: Permission denied` / `403` | Wrong account or no write access | `git remote set-url origin https://github.com/<you>/cro-intelligence.git` and check you are signed in |
| `Authentication failed` | GitHub no longer accepts account passwords over HTTPS | Create a Personal Access Token (Settings → Developer settings → Tokens) and use it as the password |
| `src refspec main does not match any` | You are on a different branch | `git branch -M main` then push again |
| `fatal: not a git repository` | You are in the wrong folder | `cd cro-intelligence` (the folder created by the clone) |

---

## What is in the commit

- Laravel 12 application code (`app/`, `routes/`, `resources/`, `config/`)
- Database migrations and seeders (`database/`)
- Compiled frontend assets (`public/build/`) — committed on purpose, so the
  site is styled even if the host skips `npm run build`
- `.env.example` with every variable documented
- `docs/` — deployment guide, walkthrough, feature checklist, hosting limits
- `composer.json` / `composer.lock` / `package.json` / `package-lock.json`

**Not** in the commit: `.env`, `vendor/`, `node_modules/`, logs, caches,
database dumps. All excluded by `.gitignore`.
