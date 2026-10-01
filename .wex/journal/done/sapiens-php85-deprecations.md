# PHP 8.5 implicit-nullable deprecation

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:sapiens

## Context

Reported by the `symfony-loader` agent (commit `92ff57c`), who cleared its own and listed those still surfacing in suites that load this package.

## Task

`AbstractTranslationCommand` line 15: make the parameter explicitly nullable (`Type $x = null` → `?Type $x = null`). Then scan `src/` for others not loaded by any test.

## Done

`AbstractTranslationCommand::__construct()` takes `?string $name`. No other implicit nullable in `src/` nor `tests/`. Two more deprecations of this suite cleared on the way: `null` used as an array offset in `AbstractTranslationTest`'s catalogue stubs (now the `messages` domain), and a `setAccessible()` call with no effect since PHP 8.1. What still surfaces comes from `symfony-testing`, which has its own todo.
