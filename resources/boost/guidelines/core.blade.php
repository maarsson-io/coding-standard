# maarsson/coding-standard

This package provides shared code-quality configurations for Laravel/PHP, JS/TS/Vue and CSS/SCSS, and Git hooks.

Use the `code-quality-checks` skill before changing code and when verifying changes covered by these configurations.

If this package is installed but its rulesets have not been added to the consumer project, report this as a configuration error.

Always use the project's installed rulesets and configured tools for code-quality checks. These take precedence over default Laravel conventions, including Laravel Pint, and other generic formatting recommendations. Resolve code-quality failures by changing the code to comply with the configured rules. Do not modify rulesets, disable or weaken rules, or add suppressions to make failing code pass.

Do not consider a code change complete if it introduces ruleset violations or its compliance has not been adequately verified. For code changes, select checks appropriate to the affected code and risk; running the entire toolset is not always necessary. For a code review, run all configured code-quality checks and tests over their configured scope. A review is only green when verification establishes that the reviewed changes introduce no new failures. Report pre-existing failures separately; they do not prevent a green review. If checks could not run or new and pre-existing failures cannot be reliably distinguished, report the review as unverified.
