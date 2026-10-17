// Run with Playwright CLI run-code --filename in an isolated loopback prototype tab.
// Checks DESIGN.md §§5, 7, 9 and existing demo interactions; no application services run.
async function checkWireframeDesign(page) {
  const base = new URL("./", page.url()).href;
  const access = base + "access-wireframe.html";
  const student = base + "student-dashboard-prototype-notification-bell.html";
  const admin = base + "admin-wireframe.html";
  const errors = [];
  const consoleErrors = [];
  page.on("pageerror", (error) => errors.push(error.message));
  page.on("console", (message) => {
    if (message.type() === "error") consoleErrors.push(message.text());
  });
  let checks = 0;
  let screens = 0;
  let scrollingTables = 0;
  function check(value, message) {
    if (!value) throw new Error(message);
    checks++;
  }
  async function role(role) {
    await page.goto(access);
    await page.evaluate((role) => {
      sessionStorage.setItem(
        "ewaste-wireframe-session",
        JSON.stringify({ role, email: role + "@example.test" }),
      );
    }, role);
    await page.goto(role === "admin" ? admin : student);
  }
  async function studentPage(id) {
    if (!(await page.locator("#sidebar").evaluate((nav) => nav.open)))
      await page.locator("#menuToggle").click();
    if (id === "details") {
      await page.locator('.nav [data-go="history"]').click();
      await page.locator("[data-tx]").first().click();
    } else await page.locator('.nav [data-go="' + id + '"]').click();
  }
  async function adminPage(id) {
    const parents = {
      "bin-form": "bins",
      "bin-show": "bins",
      "transaction-show": "transactions",
      "user-form": "users",
      "org-form": "organizations",
      "reward-form": "rewards",
    };
    if (parents[id]) {
      await adminPage(parents[id]);
      await page
        .locator('.screen.active [data-screen="' + id + '"]')
        .first()
        .click();
      return;
    }
    if (!(await page.locator("#admin-navigation").evaluate((nav) => nav.open)))
      await page.locator("#admin-menu").click();
    await page.locator('.wf-sidebar [data-screen="' + id + '"]').click();
  }
  async function audit(context) {
    screens++;
    const layout = await page.evaluate(() => {
      const visible = (el) => !!el.getClientRects().length;
      const controls = [
        ...document.querySelectorAll(
          'button:not(:disabled), input:not([type="checkbox"]), select, textarea, summary',
        ),
      ].filter(visible);
      const fields = [
        ...document.querySelectorAll("input, select, textarea"),
      ].filter(visible);
      const tables = [...document.querySelectorAll("table")].filter(visible);
      return {
        width: document.documentElement.scrollWidth,
        viewport: innerWidth,
        headings: [...document.querySelectorAll("h1")].filter(visible).length,
        invalidNumber: document.body.innerText.includes("NaN"),
        shortControls: controls
          .filter((el) => el.getBoundingClientRect().height < 44)
          .map((el) => el.outerHTML.slice(0, 100)),
        unlabeledFields: fields
          .filter((el) => !el.labels?.length && !el.getAttribute("aria-label"))
          .map((el) => el.id),
        inaccessibleTables: tables.filter((table) => {
          const region = table.closest('[role="region"]');
          return (
            !region ||
            region.tabIndex !== 0 ||
            !region.getAttribute("aria-label")
          );
        }).length,
        stylesheets: [
          ...document.querySelectorAll('link[rel="stylesheet"]'),
        ].map((link) => new URL(link.href).pathname.split("/").pop()),
      };
    });
    check(layout.width <= layout.viewport, context + ": page-wide overflow");
    check(layout.headings === 1, context + ": needs one visible page heading");
    check(!layout.invalidNumber, context + ": invalid displayed number");
    check(
      !layout.shortControls.length,
      context + ": short targets " + layout.shortControls.join(", "),
    );
    check(
      !layout.unlabeledFields.length,
      context + ": unlabeled fields " + layout.unlabeledFields.join(", "),
    );
    check(
      layout.inaccessibleTables === 0,
      context + ": inaccessible table region",
    );
    check(
      layout.stylesheets.length === 1 &&
        layout.stylesheets[0] === "wireframe.css",
      context + ": must use the shared stylesheet",
    );
    for (const region of await page.locator(".table-wrap:visible").all()) {
      if (await region.evaluate((el) => el.scrollWidth > el.clientWidth)) {
        await region.focus();
        await page.keyboard.press("ArrowRight");
        await page.waitForFunction(
          () =>
            document.activeElement?.classList.contains("table-wrap") &&
            document.activeElement.scrollLeft > 0,
        );
        check(
          await region.evaluate(
            (el) => el === document.activeElement && el.scrollLeft > 0,
          ),
          context + ": keyboard table scrolling",
        );
        scrollingTables++;
        await region.evaluate((el) => {
          el.scrollLeft = 0;
        });
      }
    }
  }

  await page.goto(access);
  await page.evaluate(() => {
    sessionStorage.clear();
    sessionStorage.setItem(
      "ewaste-wireframe-registration",
      JSON.stringify({
        firstName: "Casey",
        lastName: "Demo",
        studentNumber: "2026-DEMO-0042",
        email: "casey@example.test",
        organization: "CCS",
        status: "pending",
        submittedAt: "2026-10-17T08:00:00.000Z",
        reviewedAt: null,
        reviewedBy: null,
        note: "",
      }),
    );
  });
  for (const width of [320, 390, 768, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    await role("admin");
    const adminScreens = await page
      .locator(".screen")
      .evaluateAll((sections) =>
        sections.map((section) => section.id.slice(2)),
      );
    for (const id of adminScreens) {
      await adminPage(id);
      await audit("Admin " + id + " at " + width);
    }
    await role("student");
    const studentScreens = await page
      .locator(".nav [data-go]")
      .evaluateAll((links) => links.map((link) => link.dataset.go));
    for (const id of [...studentScreens, "details"]) {
      await studentPage(id);
      await audit("Student " + id + " at " + width);
    }
    for (const id of [
      "login",
      "register",
      "registration-status",
      "forgot",
      "sent",
      "setup",
      "reset",
      "success",
      "unusable",
      "denied",
      "unavailable",
    ]) {
      await page.goto(access + "#" + id);
      await audit("Access " + id + " at " + width);
    }
  }

  await page.setViewportSize({ width: 390, height: 844 });
  await role("student");
  await studentPage("history");
  await page.getByLabel("Search transactions").fill("no-such-transaction");
  check(
    await page.getByText("No transactions found for this filter.").isVisible(),
    "History empty filter retains feedback",
  );
  await page.getByLabel("Search transactions").fill("EW-2026-0018");
  check(
    (await page.locator("#historyResults tbody tr").count()) === 1,
    "History search still filters transactions",
  );
  await studentPage("rewards");
  await page.getByLabel("Show rewards").selectOption("available");
  check(
    (await page.locator(".reward-card button:disabled").count()) === 0,
    "In-stock filter still excludes unavailable rewards",
  );
  await page.locator('[data-reward="1"]').click();
  check(
    await page.getByRole("dialog").isVisible(),
    "Reward confirmation opens a native dialog",
  );
  check(
    await page
      .locator("#cancelModal")
      .evaluate((button) => button === document.activeElement),
    "Dialog starts on cancel",
  );
  await page.keyboard.press("Tab");
  check(
    await page.evaluate(() => !!document.activeElement.closest("dialog")),
    "Dialog actions are reachable by keyboard",
  );
  await page.keyboard.press("Escape");
  await page.locator("#rewardDialog").waitFor({ state: "detached" });
  check(
    await page
      .locator('[data-reward="1"]')
      .evaluate((button) => button === document.activeElement),
    "Escape restores the reward trigger",
  );
  await page.locator('[data-reward="1"]').click();
  await page.locator("#cancelModal").click();
  await page.locator("#rewardDialog").waitFor({ state: "detached" });
  check(
    (await page.locator(".reward-card").first().textContent()).includes(
      "5 available",
    ),
    "Cancel preserves demo inventory",
  );
  await page.locator('[data-reward="1"]').click();
  await page.locator("#confirmModal").click();
  check(
    await page
      .getByRole("heading", { name: "Reward redemption", exact: true })
      .isVisible(),
    "Confirmation still opens redemption history",
  );
  check(
    (await page.locator(".table-wrap tbody").textContent()).includes(
      "Campus Meal Voucher",
    ),
    "Confirmed demo reward remains in history",
  );
  await audit("Confirmed redemption at 390");
  await studentPage("points");
  check(
    (await page.locator(".points-panel .stat-num").textContent()).trim() ===
      "590 pts",
    "Existing demo balance changes once after confirmation",
  );
  await studentPage("notifications");
  await page.locator("#markAll").click();
  await page.waitForTimeout(3600);
  check(
    await page.getByRole("status").isVisible(),
    "Feedback remains visible instead of expiring",
  );
  check(
    (await page.locator(".notification.unread").count()) === 0,
    "Read state remains connected to the notification bell",
  );
  await page.goto(access + "#login");
  await page.keyboard.press("Tab");
  const focus = await page.locator("#login-email").evaluate((input) => {
    input.focus();
    const style = getComputedStyle(input);
    return { width: style.outlineWidth, color: style.outlineColor };
  });
  check(
    focus.width === "3px" && focus.color === "rgb(0, 0, 0)",
    "Keyboard focus uses a visible black outline",
  );
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.locator('#login-form [type="submit"]').hover();
  await page.mouse.down();
  check(
    await page
      .locator('#login-form [type="submit"]')
      .evaluate((button) => getComputedStyle(button).transform === "none"),
    "Reduced motion disables pressed movement",
  );
  await page.mouse.up();
  await page.emulateMedia({ reducedMotion: "no-preference" });
  await page.evaluate(() => sessionStorage.clear());
  check(errors.length === 0, "Browser script errors: " + errors.join("; "));
  check(
    consoleErrors.length === 0,
    "Browser console errors: " + consoleErrors.join("; "),
  );
  return {
    screens,
    checks,
    scrollingTables,
    browserErrors: errors.length,
    consoleErrors: consoleErrors.length,
  };
}
