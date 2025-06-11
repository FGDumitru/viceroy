# Advanced Benchmarking Documentation: `benchmark_extra.php`

## Table of Contents

- [1. Overview: Purpose and Advanced Design](#1-overview-purpose-and-advanced-design)
- [2. Requirements and Setup](#2-requirements-and-setup)
- [3. Key Features and Capabilities](#3-key-features-and-capabilities)
- [4. Command-Line Interface and Options](#4-command-line-interface-and-options)
- [5. Database Usage and Data Management](#5-database-usage-and-data-management)
- [6. Benchmarking Workflow](#6-benchmarking-workflow)
- [7. Statistics, Output, and Reporting](#7-statistics-output-and-reporting)
- [8. Usage Examples](#8-usage-examples)
- [9. Error Handling and Edge Cases](#9-error-handling-and-edge-cases)
- [10. Project Context and Integration](#10-project-context-and-integration)
- [11. Additional Technical Insights](#11-additional-technical-insights)

---

## 1. Overview: Purpose and Advanced Design

`benchmark_extra.php` is an advanced benchmarking tool for evaluating the performance and accuracy of Large Language Model (LLM) endpoints (OpenAI-compatible) across a set of questions. It extends basic benchmarking with advanced features for model filtering, multi-attempt validation, detailed statistics, speed enforcement, and flexible output. The script is designed for systematic, repeatable, and extensible benchmarking in multi-model environments, supporting persistent data storage and robust reporting.

---

## 2. Requirements and Setup

- **Files Required:**
  - `benchmark_data.json`: Contains the set of questions and metadata for benchmarking.
  - `.bearer_token`: (Optional) Bearer token for authenticating with custom endpoints.
- **Dependencies:**
  - PHP (version 7.4+ recommended)
  - SQLite3 extension for PHP
- **Endpoint Configuration:**
  - Supports OpenAI-compatible endpoints.
  - Custom endpoints and bearer tokens can be configured via environment variables or config files.
- **Setup Steps:**
  1. Place `benchmark_extra.php`, `benchmark_data.json`, and (optionally) `.bearer_token` in the working directory.
  2. Ensure PHP and SQLite3 are installed.
  3. Configure endpoint and authentication as needed.

---

## 3. Key Features and Capabilities

- **Model Filtering:**
  - Include/exclude models using shell-style wildcards (`--model`, `--ignore`).
  - Explicit model lists via `--specific-models`.
  - Allows benchmarking of specific models or groups of models based on patterns.
- **Multi-Attempt Validation:**
  - Multiple response attempts per question (`--total-required-answers`, `--required-correct-answers`).
  - Tracks correctness across attempts, providing a more robust evaluation.
- **Speed Limit Enforcement:**
  - Enforces minimum prompt and generation speeds (`--min-prompt-speed`, `--min-token-speed`).
  - Option to override with `--ignore-speed-limits`, allowing flexibility in benchmarking.
- **Detailed Metrics:**
  - Captures timing data (prompt time, generation time), token counts, and correctness for each attempt.
  - Provides comprehensive insights into model performance.
- **Flexible Output:**
  - Displays overall stats, category/subcategory breakdowns.
  - Exports results in CSV/JSON formats for further analysis.
- **Persistent Data:**
  - Uses a local SQLite database for storing benchmark runs and statistics.
  - Supports resetting data globally or per model, ensuring data management flexibility.
- **Progress Tracking:**
  - Visual progress bars and ETA estimates during execution, enhancing user experience.
- **Question Preparation:**
  - Handles MCQ shuffling, prompt formatting, and response validation (including XML tag extraction).
  - Ensures consistent and fair evaluation of models.
- **Stats Display:**
  - Multiple mutually exclusive stats modes (`--stats`, `--astats`, `--cstats`), with detailed tables and category breakdowns.
  - Allows users to choose the level of detail in statistical reporting.
- **Error Handling:**
  - Gracefully manages API errors, timeouts, and database issues, ensuring reliability.
  - Includes robust error handling mechanisms for API interactions and data storage.

---

## 4. Command-Line Interface and Options

Invoke the script as:

```sh
php benchmark_extra.php [options]
```

### Options

| Option | Alias | Description |
|--------|-------|-------------|
| `--model=pattern` | `-m=pattern` | Filter models using shell-style wildcards (e.g., `*-13b`) |
| `--ignore=pattern` | `-i=pattern` | Exclude models matching pattern (e.g., `llama*`) |
| `--specific-models=model1,model2` | | Benchmark only the specified models |
| `--reset` | | Clear all previous benchmark data from database |
| `--reset-model=name` | `-rm=name` | Clear data for specific model only |
| `--total-required-answers=N` | `-a=N` | Number of attempts per question (default: 1, range: 1-10) |
| `--required-correct-answers=N` | `-c=N` | Minimum correct answers required (default: 1) |
| `--stats` | | Display aggregated model statistics table and exit |
| `--astats` | | Show all stats parsed per category and subcategory, then exit |
| `--cstats` | | Show stats per main category only, then exit |
| `--details` | `-d` | Show detailed performance breakdown by category/subcategory |
| `--exclude-subcategories` | `-e` | Show category-level stats only (must be used with `-d`) |
| `--qcnt=N` | `-q=N` | Limit benchmark to first N questions (default: all) |
| `--ignore-speed-limits` | `-isl` | Skip token generation speed validation |
| `--max-context=N` | `-mc=N` | Maximum output tokens per response (default: 4096) |
| `--min-prompt-speed=N` | `-ps=N` | Minimum tokens/sec for prompt processing (default: 3) |
| `--min-token-speed=N` | `-ts=N` | Minimum tokens/sec for generation (default: 3) |
| `--min-eval-attempts=N` | `-ea=N` | Minimum attempts before speed evaluation (default: 1) |
| `--verbose` | `-v` | Show detailed question/response information |
| `--endpoint=URL` | | Specify the OpenAI-compatible endpoint URL to use for benchmarking |
| `--bearer=TOKEN` | | Specify the bearer token for authentication with the endpoint |
| `--sleep-seconds=N` | | Sleep for N seconds after each LLM query (default: 0, i.e., no sleep) |
| `--list-models` | | List all available models from the selected endpoint and exit |
| `--help` | `-h` | Show help message and exit |


**About `--list-models`:**

The `--list-models` option allows you to quickly list all available models from the selected endpoint (or the default endpoint if none is specified) and then exit. This is useful for discovering which models are accessible for benchmarking. You can combine `--list-models` with `--endpoint` and `--bearer` to target a specific endpoint and provide authentication. If authentication fails or the endpoint is unreachable, an error message will be displayed. No benchmarks or other script logic will be run when this option is used; the script exits immediately after listing models.

---

## 5. Database Usage and Data Management

- **SQLite Database:** 
  - Stores all benchmark runs, statistics, and metadata in `benchmark.db`.
  - Schema evolves automatically (adds columns as needed).
- **Tables:**
  - `benchmark_runs`: Individual attempts (model, question, response, correctness, timing, category).
  - `model_stats`: Aggregated statistics per model.
  - `valid_questions`: Tracks manually vetted questions.
- **Resetting Data:**
  - Use `--reset` to clear all data, or `--reset-model` to clear data for a specific model.

---

## 6. Benchmarking Workflow

```mermaid
flowchart TD
    A[Initialization] --> B[Model Selection & Filtering]
    B --> C[Question Preparation (MCQ shuffling, prompt formatting)]
    C --> D[Multi-Attempt Validation]
    D --> E[Speed Limit Enforcement]
    E --> F[Response Validation (XML extraction, correctness)]
    F --> G[Data Recording (SQLite)]
    G --> H[Progress Tracking & Stats Reporting]
```

- **Initialization:** Loads config, parses CLI options, prepares database.
- **Model Selection:** Retrieves models, applies filters, enforces speed limits.
- **Question Preparation:** Formats prompts, shuffles MCQs, sets up validation.
- **Multi-Attempt Validation:** Sends multiple attempts per question, tracks correctness.
- **Speed Enforcement:** Validates prompt and generation speeds.
- **Response Validation:** Extracts and normalizes responses (supports `<response>...</response>` tags).
- **Data Recording:** Stores all attempts and stats in SQLite.
- **Progress & Stats:** Shows progress bars, ETA, and detailed stats.

---

## 7. Statistics, Output, and Reporting

- **Stats Modes:**
  - `--stats`: Aggregated model statistics.
  - `--astats`: All stats parsed per category and subcategory.
  - `--cstats`: Stats per main category only.
  - `--details`: Category/subcategory breakdowns (can be used with `--exclude-subcategories`).
- **Output Formats:**
  - Human-readable tables (console).
  - CSV and JSON export for further analysis (e.g., redirecting output to a file).
- **Metrics Captured:**
  - Accuracy, timing (prompt/gen), token counts, per-category/subcategory performance.
  - Detailed breakdowns of model performance across different categories and subcategories.

---

## 8. Usage Examples

- **Basic benchmark run:**
  ```sh
  php benchmark_extra.php
  ```
- **Benchmark specific models with 3 attempts per question:**
  ```sh
  php benchmark_extra.php --model=*-13b --total-required-answers=3
  ```
- **Benchmark only selected models:**
  ```sh
  php benchmark_extra.php --specific-models=modelA,modelB
  ```
- **Show aggregated statistics from previous runs:**
  ```sh
  php benchmark_extra.php --stats
  ```
- **Show detailed statistics (all categories and subcategories):**
  ```sh
  php benchmark_extra.php --astats
  ```
- **Show statistics per main category only:**
  ```sh
  php benchmark_extra.php --cstats
  ```
- **Benchmark first 10 questions, ignoring speed limits:**
  ```sh
  php benchmark_extra.php --qcnt=10 --ignore-speed-limits
  ```
- **Export results to CSV:**
  ```sh
  php benchmark_extra.php --stats > results.csv
  ```
- **List all available models (optionally with endpoint and bearer):**
  ```sh
  php benchmark_extra.php --list-models
  php benchmark_extra.php --list-models --endpoint=https://api.example.com/v1 --bearer=YOUR_TOKEN
  ```

- **List all available models (optionally with endpoint and bearer):**
```sh
php benchmark_extra.php --list-models
php benchmark_extra.php --list-models --endpoint=https://api.example.com/v1 --bearer=YOUR_TOKEN
```

---

## 9. Error Handling and Edge Cases

- **Help/Usage:** `--help` prints detailed instructions.
- **Missing Data:** Exits with error if `benchmark_data.json` is missing or invalid.
- **Database Errors:** Uses transactions and try/catch for integrity.
- **API Errors:** Handles timeouts, exceptions, and marks failed attempts.
- **Speed Limit Violations:** Skips models that do not meet speed requirements (unless overridden).
- **Schema Evolution:** Adds missing columns as needed.
- **Response Validation:** Handles MCQ, free-form, and wildcard answers; extracts from XML tags.

---

## 10. Project Context and Integration

- **Part of a larger LLM benchmarking suite ("Viceroy").**
- **Integrates with custom `SQLiteDatabase` and OpenAI-compatible endpoint abstraction.**
- **Intended for researchers, developers, and QA engineers evaluating LLMs.**

---

## 11. Additional Technical Insights

- **Prompt Engineering:** Prompts are crafted for concise, direct answers; explicit response formatting.
- **MCQ Handling:** Shuffles options, validates normalized answers.
- **Progress Feedback:** Emoji-based progress bars, ETA.
- **Verbose Output:** Detailed per-question/response info.
- **Extensibility:** Easy to add new models, question types, and metrics.
- **Data Integrity:** All writes are transactional.
- **Strict Response Format:** Enforces `<response>...</response>` for reliable parsing.
- **Performance Tracking:** Tracks both accuracy and speed.

---

**End of Advanced Documentation**