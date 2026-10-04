#!/bin/bash

# Status line script for Claude Code in DDEV environments
# Format: CWD | Model | Progress Bar | Percentage | Tokens | Git Branch | Project Name

input=$(cat)

# ANSI codes
DIM=$'\e[2m'
RESET=$'\e[0m'
GREEN=$'\e[32m'
YELLOW=$'\e[33m'
RED=$'\e[31m'

# Extract JSON values
model=$(echo "$input" | jq -r '.model.display_name')
cwd=$(echo "$input" | jq -r '.workspace.current_dir')
project_dir=$(echo "$input" | jq -r '.workspace.project_dir')

# Get project name from directory
project_name=$(basename "$project_dir")

# Calculate context window usage
context_pct=0
tokens=""
progress_bar=""
pct_color="$DIM"
usage=$(echo "$input" | jq '.context_window.current_usage')
size=$(echo "$input" | jq '.context_window.context_window_size')

if [ "$usage" != "null" ] && [ "$size" != "null" ] && [ "$size" -gt 0 ]; then
  current=$(echo "$usage" | jq '.input_tokens + .cache_creation_input_tokens + .cache_read_input_tokens')
  if [ "$current" != "null" ]; then
    context_pct=$((current * 100 / size))

    # Format current tokens (e.g., 45.2k)
    if [ "$current" -ge 1000000 ]; then
      current_fmt=$(awk "BEGIN {printf \"%.1fM\", $current/1000000}")
    elif [ "$current" -ge 1000 ]; then
      current_fmt=$(awk "BEGIN {printf \"%.1fk\", $current/1000}")
    else
      current_fmt="${current}"
    fi

    # Format max tokens
    if [ "$size" -ge 1000000 ]; then
      max_fmt=$(awk "BEGIN {printf \"%.0fM\", $size/1000000}")
    elif [ "$size" -ge 1000 ]; then
      max_fmt=$(awk "BEGIN {printf \"%.0fk\", $size/1000}")
    else
      max_fmt="${size}"
    fi

    tokens="${current_fmt}/${max_fmt}"

    # Build progress bar (10 chars wide)
    filled=$((context_pct / 10))
    empty=$((10 - filled))

    # Color based on usage
    if [ "$context_pct" -ge 80 ]; then
      bar_color="$RED"
      pct_color="$RED"
    elif [ "$context_pct" -ge 60 ]; then
      bar_color="$YELLOW"
      pct_color="$YELLOW"
    else
      bar_color="$GREEN"
      pct_color="$GREEN"
    fi

    progress_bar="${bar_color}["
    for ((i=0; i<filled; i++)); do progress_bar+="█"; done
    for ((i=0; i<empty; i++)); do progress_bar+="░"; done
    progress_bar+="]${RESET}"
  fi
fi

# Get git branch and dirty status
git_branch=""
if git rev-parse --git-dir > /dev/null 2>&1; then
  branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null)
  if [ -n "$branch" ]; then
    # Check for uncommitted changes
    if [ -n "$(git status --porcelain 2>/dev/null)" ]; then
      git_branch="${branch}*"
    else
      git_branch="$branch"
    fi
  fi
fi

# Build output: CWD | Model | Progress Bar | Percentage | Tokens | Git Branch | Project Name
output="${DIM}${cwd}${RESET} | ${DIM}${model}${RESET} | ${progress_bar} | ${pct_color}${context_pct}%${RESET} | ${DIM}${tokens}${RESET}"

if [ -n "$git_branch" ]; then
  output="${output} | ${DIM}${git_branch}${RESET}"
fi

output="${output} | ${DIM}${project_name}${RESET}"

echo -n "$output"
