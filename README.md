# Graph Stats

Graph Stats is a Go project that generates visualizations of model performance data. It connects to a SQLite database to fetch model statistics and benchmark runs, then creates various plots to analyze the data.

## Overview

The project consists of several components:
- Configuration loading and management
- Database connection and data fetching
- Plot generation (bar plots, scatter plots, heatmaps)

## Building and Running

1. Clone the repository:
   ```
   git clone https://github.com/yourusername/graph_stats.git
   cd graph_stats
   ```

2. Build the project:
   ```
   go build -o graph_stats cmd/graph_stats/main.go
   ```

3. Run the project:
   ```
   ./graph_stats
   ```

## Configuration

The project uses a JSON configuration file (default: `config.json`). The available configuration options are:

- `dbPath`: Path to the SQLite database file
- `outputDir`: Directory where output plots will be saved
- `topNModelsOverall`: Number of top models to display in overall plots
- `topNModelsPerCategory`: Number of top models to display per category
- `numCorrectnessQuantilesForHue`: Number of quantiles for correctness hue in plots
- `minQuestionsForCategoryPlot`: Minimum number of questions for a category to be included in plots
- `logScaleThresholdRatio`: Threshold ratio for using log scale in plots
- `figBaseWidth`: Base width for figures
- `figHeightPerItemBar`: Height per item in bar plots
- `figMinHeight`: Minimum height for figures
- `figMaxWidthBar`: Maximum width for bar plots
- `scatterFigWidth`: Width for scatter plots
- `scatterFigHeight`: Height for scatter plots
- `annotationFontSize`: Font size for annotations
- `scatterLabelFontSize`: Font size for scatter plot labels

## Usage

1. Ensure your SQLite database is set up with the required tables (`model_stats` and `benchmark_runs`).
2. Configure the `config.json` file with the appropriate settings.
3. Run the project using the command `./graph_stats`.
4. The generated plots will be saved in the specified output directory.

## Project Structure

- `cmd/graph_stats/main.go`: Entry point of the application
- `pkg/config/`: Configuration management
- `pkg/db/`: Database operations
- `pkg/plots/`: Plot generation (bar, scatter, heatmap)
