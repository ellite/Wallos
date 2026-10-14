# End-to-end (e2e) tests for web

Browser end-to-end tests for Wallos, authored with [KaneCLI](https://www.testmuai.com/kane-cli/) by TestMu AI. Each test is a plain-English `_test.md` file; KaneCLI runs it in a real Chrome browser and records each step so later runs replay without re-authoring.

| Test | What it covers |
|---|---|
| `registration_test.md` | Registering the first account on a fresh instance and logging in |
| `subscription_test.md` | Adding a monthly subscription and checking it in the subscription list |

Both tests start from a fresh instance with no users, since the first visit goes to the registration page. They complement the PHP test suite in `tests/` by going through the web UI.

## Run the tests

Build and run Wallos from this checkout on `http://localhost:8282` with an empty database:

```bash
docker build -t wallos-e2e .
docker run -d --name wallos-e2e -p 8282:80 -e TZ=UTC wallos-e2e
```

Recreate the container (`docker rm -f wallos-e2e`, then `docker run` again) before each test so it starts with no users.

### With KaneCLI

```bash
npm install -g @testmuai/kane-cli
kane-cli login
kane-cli testmd run e2e/kane/registration_test.md --agent
kane-cli testmd run e2e/kane/subscription_test.md --agent
```

### With the exported Playwright code

Each test also has an exported Playwright (JavaScript) version under `output-<test>/playwright-javascript-code/`:

```bash
cd e2e/kane/output-subscription/playwright-javascript-code
npm install
npx playwright install chromium
node test.js
```

Copy `.env.example` to `.env` and fill in your TestMu AI (LambdaTest) credentials first.

## Editing a test

Edit the step text in the `_test.md` file and run it again with `kane-cli testmd run`. Changed steps (and the steps after them) are re-recorded; unchanged steps replay from `output-<test>/`.
