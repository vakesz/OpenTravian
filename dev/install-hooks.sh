#!/bin/sh
# Opt in to the pre-commit hook. Undo: git config --unset core.hooksPath
cd "$(dirname "$0")/.." && git config core.hooksPath dev/hooks && echo "Hook enabled (skip once: git commit --no-verify)."
