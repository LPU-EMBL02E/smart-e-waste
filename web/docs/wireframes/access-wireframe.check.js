// Open access-wireframe.html over loopback HTTP, then use Playwright CLI run-code --filename with this file.
// Covers ACCESS-FLOW-PLAN.md W1 and the two prototype handoffs; no production authentication is tested.
async function checkAccessWireframe(page) {
  await page.setViewportSize({ width: 1280, height: 900 });
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
  function check(value, message) {
    if (!value) throw new Error(message);
    checks += 1;
  }
  async function login(role) {
    await page.goto(access + "#login");
    if (
      !(await page
        .locator("#prototype-controls")
        .evaluate((details) => details.open))
    )
      await page.locator("#prototype-controls summary").click();
    await page.locator('[data-fixture="' + role + '"]').click();
    check(
      (await page.locator("#login-email").inputValue()) ===
        role + "@example.test",
      "Fixture controls fill the account email",
    );
    await page.locator('#login-form [type="submit"]').click();
    await page.waitForURL(role === "student" ? student : admin);
  }
  async function fillRegistration(email, firstName = "Casey <b>Demo</b>") {
    await page.goto(access + "#register");
    await page.locator("#register-first-name").fill(firstName);
    await page.locator("#register-last-name").fill("Applicant");
    await page.locator("#register-student-number").fill("2026-DEMO-0042");
    await page.locator("#register-email").fill(email);
    await page.locator("#register-organization").selectOption("CCS");
    await page.locator("#register-password").fill("created-demo-password");
    await page.locator("#register-confirmation").fill("created-demo-password");
  }
  async function applicantLogin() {
    await page.goto(access + "#login");
    if (
      !(await page
        .locator("#prototype-controls")
        .evaluate((details) => details.open))
    )
      await page.locator("#prototype-controls summary").click();
    await page.locator("#fill-applicant").click();
    await page.locator('#login-form [type="submit"]').click();
  }
  async function savedRegistration() {
    return page.evaluate(() =>
      JSON.parse(sessionStorage.getItem("ewaste-wireframe-registration")),
    );
  }

  await page.goto(access);
  await page.reload();
  await page.evaluate(() => sessionStorage.clear());
  for (const destination of [student, admin]) {
    await page.goto(destination);
    await page.waitForURL(access + "#login");
    check(
      await page.locator('[data-view="login"]').isVisible(),
      "Guests return to shared login",
    );
  }
  await page.locator('#login-form [type="submit"]').click();
  check(
    await page.locator("#login-email-error").isVisible(),
    "Required email has an inline error",
  );
  check(
    await page.locator("#login-password-error").isVisible(),
    "Required password has an inline error",
  );
  await page.locator("#login-email").fill("invalid-email");
  await page.locator("#login-password").fill("wrong-password");
  await page.locator('#login-form [type="submit"]').click();
  check(
    await page.locator("#login-email-error").isVisible(),
    "Email format is validated",
  );

  let rejection;
  for (const email of [
    "student@example.test",
    "unknown@example.test",
    "inactive@example.test",
    "unactivated@example.test",
  ]) {
    await page.locator("#login-email").fill(email);
    await page.locator("#login-password").fill("wrong-password");
    await page.locator('#login-form [type="submit"]').click();
    const message = await page.locator("#login-form .feedback").textContent();
    rejection ??= message;
    check(
      message === rejection,
      "Credential rejection does not reveal account state",
    );
    check(
      (await page.locator("#login-email").inputValue()) === email,
      "Safe email input is preserved",
    );
    check(
      (await page.locator("#login-password").inputValue()) === "",
      "Rejected password is cleared",
    );
  }
  await page.locator("#login-email").fill("limited@example.test");
  for (let attempt = 0; attempt < 6; attempt += 1) {
    await page.locator("#login-password").fill("wrong-password");
    await page.locator('#login-form [type="submit"]').click();
  }
  check(
    (await page.locator("#login-form .feedback").textContent()).includes(
      "Too many attempts",
    ),
    "Attempt-limit feedback is available",
  );

  let acknowledgement;
  for (const email of [
    "student@example.test",
    "unknown@example.test",
    "inactive@example.test",
  ]) {
    await page.goto(access + "#forgot");
    await page.locator("#forgot-email").fill(email);
    await page.locator('#forgot-form [type="submit"]').click();
    await page.waitForURL(access + "#sent");
    const message = await page
      .locator('[data-view="sent"] .result')
      .textContent();
    acknowledgement ??= message;
    check(
      message === acknowledgement,
      "Recovery acknowledgement is account-safe",
    );
  }
  for (const mode of ["setup", "reset"]) {
    await page.goto(access + "#" + mode);
    check(
      (await page.locator("#password-heading").textContent()).startsWith(
        mode === "setup" ? "Set" : "Reset",
      ),
      "Shared form has the correct intent",
    );
    await page.locator("#new-password").fill("short");
    await page.locator("#password-confirmation").fill("short");
    await page.locator("#password-submit").click();
    check(
      await page.locator("#new-password-error").isVisible(),
      "Short passwords are rejected",
    );
    await page.locator("#new-password").fill("fixture-password");
    await page.locator("#password-confirmation").fill("different-password");
    await page.locator("#password-submit").click();
    check(
      (await page.locator("#password-confirmation-error").textContent()) ===
        "Passwords must match.",
      "Confirmation mismatch is explained",
    );
    check(
      (await page.locator("#new-password").inputValue()) === "",
      "Invalid password input is cleared",
    );
    await page.locator("#new-password").fill("fixture-password");
    await page.locator("#password-confirmation").fill("fixture-password");
    await page.locator("#password-submit").click();
    await page.waitForURL(access + "#success");
    check(
      await page.locator('[data-view="success"]').isVisible(),
      "Valid password form reaches the labelled demo result",
    );
    check(
      (await page.locator("#new-password").inputValue()) === "",
      "Password is cleared after success",
    );
  }
  await page.goto(access + "#unusable");
  await page
    .getByRole("link", { name: "Request another link", exact: true })
    .click();
  await page.waitForURL(access + "#forgot");
  check(
    await page.locator('[data-view="forgot"]').isVisible(),
    "Unusable link offers recovery",
  );
  await page.goto(access + "#unavailable");
  await page.getByRole("link", { name: "Try again", exact: true }).click();
  await page.waitForURL(access + "#login");
  check(
    await page.evaluate(() => sessionStorage.length === 0),
    "No credentials or passwords were stored",
  );

  for (const role of ["student", "admin"]) {
    await login(role);
    const destination = role === "student" ? student : admin;
    check(
      await page
        .getByRole("heading", {
          name: role === "student" ? "Dashboard home" : "Dashboard",
          exact: true,
        })
        .isVisible(),
      "Login opens the correct role dashboard",
    );
    check(
      await page.evaluate(
        () =>
          sessionStorage.length === 1 &&
          ["student", "admin"].includes(
            JSON.parse(sessionStorage.getItem("ewaste-wireframe-session"))
              ?.role,
          ),
      ),
      "Only the demo identity is stored",
    );
    await page.goto(role === "student" ? admin : student);
    await page.waitForURL(access + "#denied");
    check(
      await page.locator('[data-view="denied"]').isVisible(),
      "Wrong-role page returns to access denied",
    );
    await page
      .getByRole("link", { name: "Return to dashboard", exact: true })
      .click();
    await page.waitForURL(destination);
    await page.goto(destination + "?history-check=1");
    if (role === "student") await page.locator("#logout").click();
    else await page.locator(".wf-topbar [data-logout]").click();
    await page.waitForURL(access + "#login");
    check(
      await page.evaluate(() => sessionStorage.length === 0),
      "Logout clears the demo role",
    );
    await page.goBack();
    await page.waitForURL(access + "#login");
    check(
      await page.locator('[data-view="login"]').isVisible(),
      "Back after logout cannot reopen the dashboard",
    );
  }
  await login("admin");
  await page.locator(".wf-sidebar [data-logout]").click();
  await page.waitForURL(access + "#login");
  check(
    await page.evaluate(() => sessionStorage.length === 0),
    "Admin sidebar logout also clears the demo role",
  );

  await page.setViewportSize({ width: 390, height: 844 });
  await login("student");
  check(
    await page.locator("#sidebar").evaluate((navigation) => navigation.open),
    "Student navigation is visible on mobile",
  );
  await page.locator("#menuToggle").click();
  check(
    await page.locator("#sidebar").evaluate((navigation) => !navigation.open),
    "Native student navigation can collapse",
  );
  await page.locator("#menuToggle").click();
  await page.locator("#logout").click();
  await page.waitForURL(access + "#login");
  check(
    await page.locator('[data-view="login"]').isVisible(),
    "Student mobile navigation returns to shared login",
  );
  await login("admin");
  await page.locator(".wf-topbar [data-logout]").click();
  await page.waitForURL(access + "#login");
  check(
    await page.locator('[data-view="login"]').isVisible(),
    "Admin mobile logout returns to shared login",
  );

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto(access + "#register");
  await page.locator('#register-form [type="submit"]').click();
  check(
    await page.locator("#register-student-number-error").isVisible(),
    "Registration requires a student number for verification",
  );
  check(
    await page.locator("#register-organization-error").isVisible(),
    "Registration requires an organization",
  );
  check(
    (await page.locator('#register-form [name="role"]').count()) === 0,
    "Public registration has no admin role selection",
  );
  await fillRegistration("student@example.test");
  await page.locator('#register-form [type="submit"]').click();
  check(
    await page.locator("#register-email-error").isVisible(),
    "Existing demo email cannot register again",
  );
  await fillRegistration("applicant@example.test");
  await page.locator("#register-confirmation").fill("different-password");
  await page.locator('#register-form [type="submit"]').click();
  check(
    (await page.locator("#register-confirmation-error").textContent()) ===
      "Passwords must match.",
    "Registration checks password confirmation",
  );
  await fillRegistration("applicant@example.test");
  await page.locator('#register-form [type="submit"]').click();
  await page.waitForURL(access + "#registration-status");
  check(
    (await page.locator("#registration-heading").textContent()) ===
      "Pending admin review",
    "Registration starts pending admin review",
  );
  check(
    (await savedRegistration()).status === "pending",
    "Submission cannot approve its own account",
  );
  check(
    await page.evaluate(
      () =>
        !JSON.stringify({ ...sessionStorage }).includes(
          "created-demo-password",
        ),
    ),
    "Registration password is never stored",
  );
  check(
    (await page.locator("#register-password").inputValue()) === "",
    "Registration clears password fields",
  );
  await page.reload();
  check(
    (await page.locator("#registration-heading").textContent()) ===
      "Pending admin review",
    "Pending request survives a reload for review",
  );
  await applicantLogin();
  await page.waitForURL(access + "#registration-status");
  check(
    await page.evaluate(
      () => !sessionStorage.getItem("ewaste-wireframe-session"),
    ),
    "Pending applicant cannot establish a portal session",
  );

  await page.goto(access + "#reset");
  await page.locator("#new-password").fill("another-demo-password");
  await page.locator("#password-confirmation").fill("another-demo-password");
  await page.locator("#password-submit").click();
  await page.waitForURL(access + "#success");
  check(
    (await savedRegistration()).status === "pending",
    "Password reset cannot bypass admin approval",
  );
  await page.evaluate(() =>
    sessionStorage.setItem(
      "ewaste-wireframe-session",
      JSON.stringify({ role: "student", email: "applicant@example.test" }),
    ),
  );
  await page.goto(student);
  await page.waitForURL(access + "#registration-status");
  check(
    await page.locator('[data-view="registration-status"]').isVisible(),
    "Student destination rejects an unapproved applicant session",
  );
  await page.goto(admin);
  await page.waitForURL(access + "#denied");
  check(
    await page.locator("#return-dashboard").isHidden(),
    "Unapproved applicant has no dashboard return link",
  );

  await login("admin");
  await page.locator('.wf-sidebar [data-screen="registrations"]').click();
  check(
    (await page.locator("#registration-count").textContent()) === "1",
    "Admin sees the pending request count",
  );
  check(
    (await page.locator("#review-name").textContent()) ===
      "Casey <b>Demo</b> Applicant",
    "Admin sees applicant text without interpreting markup",
  );
  check(
    (await page.locator("#review-name b").count()) === 0,
    "Applicant markup is escaped in review",
  );
  await page
    .getByRole("button", { name: "Approve registration", exact: true })
    .click();
  check(
    await page.locator("#review-error").isVisible(),
    "Approval is blocked without school-record checks",
  );
  check(
    (await savedRegistration()).status === "pending",
    "Failed verification leaves the request pending",
  );
  for (const name of ["nameMatch", "studentNumberMatch", "emailMatch"])
    await page.locator('[name="' + name + '"]').check();
  await page
    .getByRole("button", { name: "Approve registration", exact: true })
    .click();
  check(
    (await savedRegistration()).status === "pending",
    "Three checks cannot substitute for all four",
  );
  await page.locator('[name="organizationMatch"]').check();
  await page.locator("#review-notes").fill("Matched the school records.");
  await page
    .getByRole("button", { name: "Approve registration", exact: true })
    .click();
  const approved = await savedRegistration();
  check(
    approved.status === "approved",
    "Verified registration can be approved",
  );
  check(
    Object.values(approved.checks).every(Boolean),
    "Approval records every verification check",
  );
  check(
    approved.reviewedBy === "admin@example.test (demo)" &&
      approved.reviewedAt.endsWith("Z"),
    "Decision records reviewer identity and UTC time",
  );
  check(
    await page.locator("#registration-review-form").isHidden(),
    "Reviewed decision cannot be silently changed",
  );
  await page.locator(".wf-topbar [data-logout]").click();
  await page.waitForURL(access + "#login");
  check(
    (await savedRegistration()).status === "approved",
    "Admin logout retains the review result",
  );
  await applicantLogin();
  await page.waitForURL(student);
  check(
    (await page.locator(".user-mini strong").textContent()) ===
      "Casey <b>Demo</b> Applicant",
    "Approved applicant reaches their own student identity",
  );
  await page.locator('[data-go="profile"]').click();
  check(
    (await page.locator(".form-grid input").nth(2).inputValue()) ===
      "applicant@example.test",
    "Profile uses the approved applicant email",
  );
  check(
    (await page.locator(".form-grid input").first().inputValue()) ===
      "Casey <b>Demo</b> Applicant",
    "Profile escapes applicant values",
  );
  await page.locator("#logout").click();
  await page.waitForURL(access + "#login");
  await page.locator("#prototype-controls summary").click();
  await page.locator("#clear-registration").click();
  check(
    (await savedRegistration()) === null,
    "Reviewer can clear the synthetic request",
  );

  await fillRegistration("denied@example.test", "Robin");
  await page.locator('#register-form [type="submit"]').click();
  await page.waitForURL(access + "#registration-status");
  await page.setViewportSize({ width: 390, height: 844 });
  await login("admin");
  await page.locator("#admin-menu").click();
  check(
    await page
      .locator("#admin-navigation")
      .evaluate((navigation) => navigation.open),
    "Native mobile admin navigation expands",
  );
  await page.locator('.wf-sidebar [data-screen="registrations"]').click();
  check(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
    "Admin registration review fits the mobile viewport",
  );
  await page
    .locator("#review-notes")
    .fill("Student number does not match school records.");
  await page
    .getByRole("button", { name: "Deny registration", exact: true })
    .click();
  check(
    (await savedRegistration()).status === "denied",
    "Admin can deny an unverified request",
  );
  await page.locator(".wf-topbar [data-logout]").click();
  await page.waitForURL(access + "#login");
  await applicantLogin();
  await page.waitForURL(access + "#registration-status");
  check(
    (await page.locator("#registration-heading").textContent()) ===
      "Registration denied",
    "Applicant sees the denial state",
  );
  check(
    (await page.locator("#registration-review-note").textContent()).includes(
      "Student number does not match school records.",
    ),
    "Applicant sees the review note",
  );
  check(
    await page.evaluate(
      () => !sessionStorage.getItem("ewaste-wireframe-session"),
    ),
    "Denied applicant cannot open a portal session",
  );

  for (const width of [320, 390, 768, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(access);
    for (const state of [
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
      await page.goto(access + "#" + state);
      check(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
        "Shared state fits viewport: " + state + " at " + width,
      );
      check(
        await page
          .locator("h1:visible")
          .evaluate((heading) => heading === document.activeElement),
        "State change focuses its heading",
      );
    }
    await page.goto(access + "#login");
    await page.locator("#prototype-controls summary").click();
    check(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
      "Reviewer controls fit viewport " + width,
    );
  }
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto(access + "#login");
  await page.reload();
  await page.keyboard.press("Tab");
  check(
    await page
      .locator("#login-email")
      .evaluate((input) => input === document.activeElement),
    "Keyboard reaches email from heading",
  );
  await page.locator(".skip").focus();
  await page.keyboard.press("Enter");
  check(
    await page
      .locator("#access")
      .evaluate((main) => main === document.activeElement),
    "Skip link focuses main content without changing the access state",
  );
  check(
    page.url() === access + "#login",
    "Skip link preserves the active view",
  );
  check(errors.length === 0, "No browser script errors: " + errors.join("; "));
  check(
    consoleErrors.length === 0,
    "No browser console errors: " + consoleErrors.join("; "),
  );
  return {
    checks,
    browserErrors: errors.length,
    consoleErrors: consoleErrors.length,
  };
}
