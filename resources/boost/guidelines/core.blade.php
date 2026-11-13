## Prvious Result

- When code uses `Prvious\Result` or the Prvious Laravel Action Pattern, activate the `prvious-result-development` skill.
- When upgrading `prvious/result` from 0.1 to 0.2, or when code calls `match()`, `map()`, `mapError()`, or `andThen()` on a `Result`, activate the `prvious-result-upgrade-0-2` skill.
- Treat those skills and the installed package source as authoritative for Result factories, generics, narrowing, extraction, and `Panic` behavior.
- Do not invent or rely on remembered Result APIs when the installed package can be inspected.
