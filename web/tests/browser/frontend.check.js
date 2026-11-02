// Playwright CLI run-code --filename against an isolated, populated loopback preview.
// Uses local preview accounts only; DESIGN.md §§5, 8–10. Reports remain deferred.
async function checkFrontend(page) {
  const base = new URL(page.url()).origin;
  if (!["127.0.0.1", "localhost"].includes(new URL(base).hostname)) {
    throw new Error("Run this check only against a local preview.");
  }
  await page.context().clearCookies();
  const errors = [];
  page.on("pageerror", (error) => errors.push(error.message));
  let checks = 0;
  let pages = 0;
  function check(condition, message) {
    if (!condition) throw new Error(message);
    checks++;
  }
  async function login(role) {
    await page.goto(base + "/login");
    await page
      .getByLabel("Email", { exact: true })
      .fill(role + "@example.test");
    await page.getByLabel("Password", { exact: true }).fill("Preview-2026!");
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await page.waitForURL(base + (role === "admin" ? "/admin" : "/home"));
  }
  async function logout() {
    await page.getByRole("button", { name: "Sign out", exact: true }).click();
    await page.waitForURL(base + "/login");
  }
  async function audit(path, width, status = 200) {
    const response = await page.goto(base + path);
    check(response.status() === status, path + ": HTTP " + response.status());
    const result = await page.evaluate(() => {
      const visible = (element) => !!element.getClientRects().length;
      const fields = [
        ...document.querySelectorAll(
          'input:not([type="hidden"]), select, textarea',
        ),
      ].filter(visible);
      const targets = [
        ...document.querySelectorAll(
          "button, summary, .nav-links a, .page-link, .button, .guest-header nav a, .data-table a",
        ),
      ].filter(visible);
      return {
        overflow: document.documentElement.scrollWidth > innerWidth,
        h1: [...document.querySelectorAll("h1")].filter(visible).length,
        unlabeled: fields
          .filter(
            (field) =>
              !field.labels?.length && !field.getAttribute("aria-label"),
          )
          .map((field) => field.name),
        short: targets
          .filter(
            (target) =>
              target.getBoundingClientRect().height < 44 ||
              target.getBoundingClientRect().width < 44,
          )
          .map((target) => target.textContent.trim()),
        styles: [...document.styleSheets].some((sheet) =>
          sheet.href?.includes("/build/assets/app-"),
        ),
        tables: [...document.querySelectorAll("table")].every((table) =>
          table.closest('[role="region"][tabindex="0"][aria-label]'),
        ),
        portal: !!document.querySelector(".portal-shell"),
        navigationIcons: [...document.querySelectorAll(".nav-links a")].every(
          (link) => {
            const icon = link.querySelector(
              'svg[aria-hidden="true"][focusable="false"]',
            );
            return (
              icon &&
              icon.getBoundingClientRect().width === 24 &&
              icon.getAttribute("stroke-width") === "2"
            );
          },
        ),
        headerBell: !!document.querySelector(
          '.portal-header a[aria-label="Notifications"][href$="/notifications"] svg',
        ),
        avatar: !!document.querySelector('.account-avatar[aria-hidden="true"]'),
        gutter:
          document.querySelector(".page-content") &&
          getComputedStyle(document.querySelector(".page-content")).paddingLeft,
      };
    });
    check(!result.overflow, path + " overflows at " + width);
    check(result.h1 === 1, path + ": one page heading");
    check(
      !result.unlabeled.length,
      path + ": unlabeled " + result.unlabeled.join(", "),
    );
    check(
      !result.short.length,
      path + ": short targets " + result.short.join(", "),
    );
    check(result.styles, path + ": compiled stylesheet missing");
    check(result.tables, path + ": table region missing");
    if (result.portal) {
      check(
        result.navigationIcons,
        path + ": wireframe navigation icons missing",
      );
      check(
        result.headerBell && result.avatar,
        path + ": wireframe header controls missing",
      );
      check(
        result.gutter ===
          (width >= 1024 ? "32px" : width >= 640 ? "24px" : "16px"),
        path + ": inconsistent page gutters",
      );
    }
    if (path === "/admin" && status === 200) {
      check(
        (await page.locator(".metric-grid .metric").count()) === 5,
        "Five collection summary tiles",
      );
      check(
        await page
          .locator(".nav-group")
          .allTextContents()
          .then(
            (groups) =>
              groups.join(",") ===
              "Monitoring,Deposits,Identity,Rewards,Account",
          ),
        "Admin wireframe navigation groups",
      );
    }
    pages++;
  }
  const adminPaths = new Set([
    "/admin",
    "/admin/users",
    "/admin/users/create",
    "/admin/organizations",
    "/admin/organizations/create",
    "/admin/bins",
    "/admin/bins/create",
    "/admin/transactions",
    "/admin/sessions/review",
    "/admin/points-rate",
    "/admin/rewards",
    "/admin/rewards/create",
    "/admin/redemptions",
    "/admin/leaderboard/organizations",
    "/notifications",
  ]);
  // Read existing fixture links rather than relying on database IDs.
  await login("admin");
  check(
    await page
      .locator('.nav-links .nav-unavailable[aria-disabled="true"]')
      .isVisible(),
    "Deferred reports are visibly unavailable",
  );
  check(
    (await page.locator('.nav-links a[href$="/admin/reports"]').count()) === 0,
    "Navigation does not lead to the report stub",
  );
  for (const index of [
    "/admin/users",
    "/admin/organizations",
    "/admin/bins",
    "/admin/transactions",
    "/admin/rewards",
  ]) {
    await page.goto(base + index);
    const links = await page
      .locator("main a[href]")
      .evaluateAll((anchors) =>
        anchors.map((anchor) => new URL(anchor.href).pathname),
      );
    for (const link of links
      .filter((link) => /\/\d+(\/edit)?$/.test(link))
      .slice(0, 2))
      adminPaths.add(link);
  }
  await logout();
  for (const width of [320, 390, 768, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    for (const path of [
      "/login",
      "/register",
      "/forgot-password",
      "/reset-password/preview-invalid?email=student%40example.test",
    ])
      await audit(path, width);
    await audit("/frontend-preview-missing", width, 404);
    await login("admin");
    for (const path of adminPaths) await audit(path, width);
    await page.goto(base + "/admin/users");
    check(
      (await page.locator(".pagination").count()) > 0,
      "Seed more than one page of QA users before running this check",
    );
    if (await page.locator(".pagination").count()) {
      await page.getByRole("link", { name: "Next", exact: true }).click();
      check(
        new URL(page.url()).searchParams.get("page") === "2",
        "Pagination reaches page two",
      );
      check(
        (
          await page.locator('.pagination [aria-current="page"]').textContent()
        ).trim() === "2",
        "Pagination marks page two",
      );
    }
    await logout();
    await login("student");
    const studentPaths = [
      "/home",
      "/profile",
      "/transactions",
      "/rewards",
      "/redemptions",
      "/leaderboard",
      "/notifications",
    ];
    await page.goto(base + "/rewards");
    const detail = await page
      .locator("main article a")
      .first()
      .getAttribute("href");
    studentPaths.push(new URL(detail).pathname);
    for (const path of studentPaths) await audit(path, width);
    await page.goto(base + "/home");
    check(
      await page.locator(".points-panel").isVisible(),
      "Student balance has focal presentation",
    );
    await audit("/admin", width, 403);
    await page
      .getByRole("link", { name: "Return to portal", exact: true })
      .click();
    await page.waitForURL(base + "/home");
    await logout();
  }
  await page.goto(base + "/login");
  await page.getByLabel("Email", { exact: true }).fill("student@example.test");
  await page
    .getByLabel("Password", { exact: true })
    .fill("wrong-preview-password");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  check(
    await page.getByRole("alert").isVisible(),
    "Validation summary remains visible",
  );
  check(
    await page.locator("#email-error").isVisible(),
    "Credential error is inline",
  );
  check(
    (await page.getByLabel("Email", { exact: true }).inputValue()) ===
      "student@example.test",
    "Safe email is preserved",
  );
  check(
    (await page.getByLabel("Password", { exact: true }).inputValue()) === "",
    "Rejected password is discarded",
  );
  await page.locator(".skip-link").focus();
  await page.keyboard.press("Enter");
  check(
    await page
      .locator("#main-content")
      .evaluate((main) => main === document.activeElement),
    "Skip link reaches main",
  );
  await login("admin");
  const before = await page.locator("[data-summary]").allTextContents();
  await page.route("**/admin/dashboard/summary", (route) =>
    route.fulfill({ status: 503, body: "{}" }),
  );
  await page
    .locator("#dashboard-refresh-feedback")
    .waitFor({ state: "visible", timeout: 15000 });
  check(
    JSON.stringify(await page.locator("[data-summary]").allTextContents()) ===
      JSON.stringify(before),
    "Failed poll retains confirmed metrics",
  );
  await page.unroute("**/admin/dashboard/summary");
  await page
    .locator("#dashboard-refresh-feedback")
    .waitFor({ state: "hidden", timeout: 15000 });
  check(true, "Successful poll clears refresh warning");
  await logout();
  check(errors.length === 0, "Browser errors: " + errors.join("; "));
  return { pages, checks, browserErrors: errors.length };
}
