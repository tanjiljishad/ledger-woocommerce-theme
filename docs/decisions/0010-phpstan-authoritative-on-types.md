# ADR 0010 — PHPStan is authoritative on types; drop the PHPCS type-hint sniff

- **Status:** accepted
- **Date:** 2026-09-07
- **Deciders:** Tanjil

## Context

Two tools read the same `@param` / `@return` PHPDoc and disagree about what a
good type looks like.

**PHPCS** — `Squiz.Commenting.FunctionComment.IncorrectTypeHint` (pulled in via
`WordPress`). It runs each PHPDoc type through `PHP_CodeSniffer\Util\Common::suggestType()`,
a pre-generics reducer, then demands the result match the PHP parameter type
hint. It has no concept of `list<string>`, `array<string>`, `array{a: int}`, or
`callable(X): Y` — anything it does not recognise that is not literally `array`
or `Foo[]` is reported as a mismatch against an `array` hint.

**PHPStan** — level 8 (ADR 0004) with `checkMissingIterableValueType` on. A bare
`@param array $x` is an error (`missingType.iterableValue`); it wants
`array<string>` / `list<string>` / a shape. It also *uses* those precise types
to find real bugs and to tell you when a guard like `is_string()` is redundant.

`plugins/core/src/Persisted_State.php` sits exactly on the fault line. Its
`merge()` and `read()` want `list<string>` and `array{options: list<string>, …}`
for PHPStan; PHPCS then rejects every one of those `@param` lines. The only
shapes that satisfy both are the imprecise ones (`string[]`, `array<mixed>`),
and picking a `@param` to dodge a PHPCS message rather than to describe the
value is the wrong reason to pick a type.

## Decision

**PHPStan is the authority on types in this codebase.** Where the two tools
conflict on a PHPDoc type, the PHPStan-correct type wins and the PHPCS sniff
that objects is disabled.

Concretely: `Squiz.Commenting.FunctionComment.IncorrectTypeHint` is excluded in
`phpcs.xml.dist`. Nothing else in `Squiz.Commenting` / `Generic.Commenting`
changes — PHPCS still requires a `@param` for every parameter, checks names and
order (`check-param-names`), enforces alignment (`check-line-alignment`), and
requires `@return`. It just no longer has an opinion about whether the type
*string* matches the PHP hint. PHPStan already enforces that the documented
types are correct and complete.

## Consequences

### Positive

- `@param`/`@return` can carry the precise generics PHPStan needs
  (`list<string>`, `array{…}`, `callable(): T`) without a second, dumber tool
  vetoing them.
- One source of truth for "is this type right?" — the analyser that actually
  models types.
- No more choosing an inaccurate type to keep PHPCS quiet.

### Negative / accepted costs

- **Nothing now checks a `@param` type *string* against its PHP parameter type
  hint.** `IncorrectTypeHint` was the only rule doing that. PHPStan still
  rejects an outright contradiction (`@param string $x` over `array $x`) via
  its native-vs-PHPDoc compatibility check, and still requires the PHPDoc type
  to be correct for how `$x` is used — but a merely loose or redundant
  annotation that does not contradict the hint (e.g. `@param iterable $x` over
  `array $x`, or a `@param` whose hint could have been narrower) is no longer
  flagged by either tool. Accepted: the value of the precise generics
  outweighs this.
- One more deviation from stock `WordPress` standard to explain to a
  contributor — this ADR is that explanation.

### Neutral

- Runtime unaffected; PHPDoc only.
- If PHPCS ever ships a generics-aware replacement, revisit.

## Alternatives considered

- **Keep the sniff, always write PHPCS-friendly types** (`string[]`, `array`,
  `array<mixed>`): rejected — it pushes imprecise types into the codebase and
  defeats level 8's value. `merge()` had already grown a two-different-types-
  per-parameter workaround purely to satisfy both tools.
- **Keep the sniff, add `// phpcs:ignore` per line:** rejected — noise on every
  generic-typed signature, and it hides real `@param` problems too.
- **Drop PHPStan precision to PHPCS's level:** rejected outright — ADR 0004.

## Notes

`merge()` in `Persisted_State.php` was simplified in the same commit now that
its parameters can both be typed `array<mixed>` honestly (it validates both
inputs), removing the `string[]` / `array<mixed>` split and an `in_array()`
dedupe loop.
