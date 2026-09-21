# Defect Report — {DOMAIN_NAME}

Filed by: test-team/{domain-slug}
Branch: test-team/{domain-slug}
Date: {DATE}

Test run summary: {N} tests, {P} passed, {F} failed.

---

## DEFECT-{N}: {short title}

- **File / function**: `path/to/File.php` — `methodName()`
- **Test**: `tests/Unit/{Domain}/....Test.php::test_...`
- **Severity**: high | medium | low
- **Expected behavior**: ...
- **Actual behavior**: ...
- **Steps to reproduce**: exact inputs / call sequence used by the failing test.
- **Why this looks like a real bug (not a bad test assumption)**: cite the
  spec/comment/adjacent behavior that justifies the expectation.

<!-- repeat one DEFECT-N block per defect found. If a test fails but the
     failure turns out to be a mistake in the test itself, fix the test
     directly instead of filing a defect for it — only file defects for
     failures that trace back to production code. -->
