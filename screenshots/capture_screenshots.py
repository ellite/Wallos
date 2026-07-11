"""
Regenerates the README screenshots against a running Wallos instance.

Requires: pip install playwright && playwright install chromium

Usage:
    WALLOS_URL=http://172.26.64.1/wallos \
    WALLOS_USERNAME=ellite \
    WALLOS_PASSWORD=screenshots \
    python3 capture_screenshots.py

WALLOS_URL defaults to http://172.26.64.1/wallos, which is the default
gateway address XAMPP on Windows is reachable at from WSL2.

Expects a test account with curated subscriptions (including one named
"Vodafone") and logos/display settings already set up the way we want
them screenshotted.
"""

import os
from pathlib import Path
from playwright.sync_api import sync_playwright

BASE = os.environ.get("WALLOS_URL", "http://172.26.64.1/wallos")
USERNAME = os.environ["WALLOS_USERNAME"]
PASSWORD = os.environ["WALLOS_PASSWORD"]
OUT = Path(__file__).resolve().parent

DESKTOP_VIEWPORT = {"width": 1280, "height": 1100}


def login(page):
    page.goto(f"{BASE}/login.php")
    page.fill('#username', USERNAME)
    page.fill('#password', PASSWORD)
    page.click('input[type="submit"]')
    page.wait_for_load_state('networkidle')


def set_theme(page, which):
    """which: 'light' or 'dark'"""
    page.goto(f"{BASE}/settings.php")
    page.click(f'#theme-{which}')
    page.wait_for_timeout(800)


def set_view(page, which):
    """which: 'list' or 'grid'. Persisted via cookie, so it carries over
    to the mobile context through storage_state."""
    page.goto(f"{BASE}/subscriptions.php")
    page.wait_for_load_state('networkidle')
    page.click(f'#view-{which}-button')
    page.wait_for_timeout(500)


def shot(page, path):
    page.wait_for_timeout(300)
    page.screenshot(path=str(OUT / path))
    print("saved", path)


def open_vodafone_popup(page):
    page.goto(f"{BASE}/subscriptions.php")
    page.wait_for_load_state('networkidle')
    card = page.locator('.subscription', has_text="Vodafone").first
    card.click(position={"x": 20, "y": 20})
    page.wait_for_timeout(500)


def close_popup(page):
    page.keyboard.press("Escape")
    page.wait_for_timeout(300)


def open_add_form(page):
    page.goto(f"{BASE}/subscriptions.php")
    page.wait_for_load_state('networkidle')
    page.click('button[onclick="addSubscription()"]')
    page.wait_for_timeout(500)
    page.fill('#name', 'HBO Max')
    page.fill('#price', '9.99')


def capture_desktop(page, dark):
    suffix = "dark" if dark else "light"

    page.goto(f"{BASE}/index.php")
    page.wait_for_load_state('networkidle')
    shot(page, f"wallos-dashboard-{suffix}.png")

    page.goto(f"{BASE}/subscriptions.php")
    page.wait_for_load_state('networkidle')
    shot(page, f"wallos-subscriptions-{suffix}.png")

    if not dark:
        page.goto(f"{BASE}/stats.php")
        page.wait_for_load_state('networkidle')
        page.evaluate("window.scrollTo(0, 200)")
        page.wait_for_timeout(200)
        shot(page, "wallos-stats.png")

        page.goto(f"{BASE}/calendar.php")
        page.wait_for_load_state('networkidle')
        shot(page, "wallos-calendar.png")

        open_add_form(page)
        shot(page, "wallos-form.png")

        open_vodafone_popup(page)
        shot(page, "wallos-subscriptions-popup.png")
        close_popup(page)


def capture_mobile(page, dark):
    suffix = "dark" if dark else "light"

    page.goto(f"{BASE}/subscriptions.php")
    page.wait_for_load_state('networkidle')
    shot(page, f"wallos-subscriptions-mobile-{suffix}.png")

    if not dark:
        open_vodafone_popup(page)
        shot(page, "wallos-subscriptions-mobile-sheet.png")
        close_popup(page)

    page.goto(f"{BASE}/index.php")
    page.wait_for_load_state('networkidle')
    shot(page, f"wallos-dashboard-mobile-{suffix}.png")


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch()

        desktop_ctx = browser.new_context(viewport=DESKTOP_VIEWPORT, locale="en-US")
        dpage = desktop_ctx.new_page()
        login(dpage)

        # find current theme selection to restore later
        dpage.goto(f"{BASE}/settings.php")
        original_theme = None
        for name in ("light", "dark", "automatic"):
            classes = dpage.locator(f"#theme-{name}").get_attribute("class") or ""
            if "selected" in classes:
                original_theme = name
        print("original theme:", original_theme)

        # find current view (list/grid) selection to restore later
        dpage.goto(f"{BASE}/subscriptions.php")
        dpage.wait_for_load_state('networkidle')
        grid_classes = dpage.locator("#view-grid-button").get_attribute("class") or ""
        original_view = "grid" if "selected" in grid_classes else "list"
        print("original view:", original_view)

        iphone = dict(p.devices["iPhone 14 Pro Max"])
        iphone["viewport"] = {"width": 430, "height": 932}

        # Screenshots use grid view throughout; the cookie this sets carries
        # over to the mobile context below via storage_state.
        set_view(dpage, "grid")

        # ---------- LIGHT ----------
        set_theme(dpage, "light")
        capture_desktop(dpage, dark=False)

        storage_state = desktop_ctx.storage_state()
        mobile_ctx = browser.new_context(storage_state=storage_state, locale="en-US", **iphone)
        mpage = mobile_ctx.new_page()
        capture_mobile(mpage, dark=False)
        mobile_ctx.close()

        # ---------- DARK ----------
        set_theme(dpage, "dark")
        capture_desktop(dpage, dark=True)

        storage_state = desktop_ctx.storage_state()
        mobile_ctx = browser.new_context(storage_state=storage_state, locale="en-US", **iphone)
        mpage = mobile_ctx.new_page()
        capture_mobile(mpage, dark=True)
        mobile_ctx.close()

        # ---------- restore original theme/view ----------
        if original_theme:
            set_theme(dpage, original_theme)
        set_view(dpage, original_view)

        desktop_ctx.close()
        browser.close()


if __name__ == "__main__":
    main()
