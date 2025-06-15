#!/usr/bin/env python3
import sqlite3
import pandas as pd
import matplotlib.pyplot as plt
import matplotlib.colors as mcolors
import seaborn as sns
import os
import shutil
import numpy as np
from matplotlib.ticker import LogLocator, NullFormatter, ScalarFormatter
from adjustText import adjust_text

# --- Configuration ---
DB_PATH = 'benchmark.db'
OUTPUT_DIR = 'benchmark_graphs'
TOP_N_MODELS_OVERALL = 50
TOP_N_MODELS_PER_CATEGORY = 15
NUM_CORRECTNESS_QUANTILES_FOR_HUE = 5

MIN_QUESTIONS_FOR_CATEGORY_PLOT = 3
LOG_SCALE_THRESHOLD_RATIO = 20

FIG_BASE_WIDTH = 12
FIG_HEIGHT_PER_ITEM_BAR = 0.35
FIG_MIN_HEIGHT = 7
FIG_MAX_WIDTH_BAR = 25

SCATTER_FIG_WIDTH = 18
SCATTER_FIG_HEIGHT = 15

sns.set_theme(style="whitegrid", palette="muted")
plt.rcParams.update({
    'font.size': 10,
    'axes.titlesize': 14,
    'axes.labelsize': 12,
    'xtick.labelsize': 9,
    'ytick.labelsize': 10,
    'legend.fontsize': 9,
    'figure.titlesize': 15,
    'figure.dpi': 150,
    'lines.linewidth': 1.8,
    'lines.markersize': 5,
})
ANNOTATION_FONT_SIZE = 7.5
SCATTER_LABEL_FONT_SIZE = 6.5

os.makedirs(OUTPUT_DIR, exist_ok=True)

def clear_output_directory(directory):
    if os.path.exists(directory):
        for item in os.listdir(directory):
            item_path = os.path.join(directory, item)
            if os.path.isfile(item_path) or os.path.islink(item_path):
                os.unlink(item_path)
            elif os.path.isdir(item_path):
                shutil.rmtree(item_path)
        print(f"Cleared output directory: {directory}")
    else:
        os.makedirs(directory, exist_ok=True)
        print(f"Created output directory: {directory}")

def save_plot(figure, filename_base, title):
    figure.suptitle(title, fontsize=plt.rcParams['figure.titlesize'])
    try:
        plt.tight_layout(rect=[0, 0.03, 1, 0.95])
    except UserWarning as e:
        print(f"Warning during tight_layout for {filename_base}: {e}")
    filepath = os.path.join(OUTPUT_DIR, f"{filename_base}.png")
    plt.savefig(filepath)
    print(f"Saved: {filepath}")
    plt.close(figure)

def apply_log_scale_if_needed(ax, series_data, axis='y'):
    numeric_series = pd.to_numeric(series_data, errors='coerce')
    positive_data = numeric_series[numeric_series > 0]
    
    if not positive_data.empty:
        min_val = positive_data.min()
        max_val = positive_data.max()
        if max_val > 0 and min_val > 0 and (max_val / min_val) > LOG_SCALE_THRESHOLD_RATIO :
            if axis == 'y':
                ax.set_yscale('log')
                ax.yaxis.set_major_formatter(ScalarFormatter())
                ax.yaxis.set_minor_formatter(NullFormatter())
            elif axis == 'x':
                ax.set_xscale('log')
                ax.xaxis.set_major_formatter(ScalarFormatter())
                ax.xaxis.set_minor_formatter(NullFormatter())
            print(f"Applied log scale to {axis}-axis for plot (min_pos={min_val:.2f}, max={max_val:.2f})")
            return True
    return False

def plot_overall_model_correctness_bar(df_model_stats):
    if df_model_stats.empty or 'percentage_correct' not in df_model_stats.columns:
        print("Skipping overall model correctness bar: No data or 'percentage_correct' column missing.")
        return

    df_sorted_overall = df_model_stats.sort_values('percentage_correct', ascending=False)
    
    df_top_n = df_sorted_overall.head(TOP_N_MODELS_OVERALL)
    num_models_plot = len(df_top_n)
    fig_height = max(FIG_MIN_HEIGHT, num_models_plot * FIG_HEIGHT_PER_ITEM_BAR)
    fig_width = FIG_BASE_WIDTH

    fig, ax = plt.subplots(figsize=(fig_width, fig_height))
    bars = sns.barplot(x='percentage_correct', y='model_id', data=df_top_n, ax=ax, 
                       palette="viridis_r", hue='model_id', dodge=False, legend=False,
                       order=df_top_n['model_id'].tolist())
                       
    ax.set_xlabel("Correctness (%)")
    ax.set_ylabel("Model ID")
    ax.set_xlim(0, 105)
    ax.grid(True, axis='x', linestyle='--', alpha=0.7)

    for i, (index, row) in enumerate(df_top_n.iterrows()):
        if i < len(bars.patches):
            bar = bars.patches[i]
            percentage = row['percentage_correct']
            label_text = f"{percentage:.1f}%"
            ax.text(bar.get_width() + 0.5, bar.get_y() + bar.get_height() / 2,
                    label_text, va='center', ha='left', fontsize=ANNOTATION_FONT_SIZE)
    
    save_plot(fig, "overall_model_correctness_top_n_bar", f"Top {TOP_N_MODELS_OVERALL} Models by Correctness")

def plot_overall_model_speeds_scatter(df_model_stats, label_points=False): # Ensure this line has label_points
    if df_model_stats.empty:
        print("Skipping overall model speeds scatter: No data.")
        return

    df_speeds = df_model_stats.copy()
    speed_cols_check = ['avg_prompt_eval_per_second', 'avg_tokens_per_second', 'model_id']
    # Add these if they exist, for hue/size, but don't fail if they don't
    if 'percentage_correct' in df_speeds.columns:
        speed_cols_check.append('percentage_correct')
    if 'total_questions' in df_speeds.columns:
        speed_cols_check.append('total_questions')
        
    for col in speed_cols_check:
        if col not in df_speeds.columns:
            # This is fine for optional columns like percentage_correct/total_questions
            print(f"Info: Column '{col}' not found in model_stats for scatter plot, will proceed without it if optional.")
            if col in ['avg_prompt_eval_per_second', 'avg_tokens_per_second', 'model_id']: # these are critical
                 print(f"Error: Critical Column '{col}' not found in model_stats for scatter plot.")
                 return
        elif col not in ['model_id']: # Don't convert model_id to numeric
             df_speeds[col] = pd.to_numeric(df_speeds[col], errors='coerce')


    df_plot = df_speeds.dropna(subset=['avg_prompt_eval_per_second', 'avg_tokens_per_second'])
    df_plot_for_log = df_plot[(df_plot['avg_prompt_eval_per_second'] > 0) & (df_plot['avg_tokens_per_second'] > 0)].copy()
    
    if df_plot.empty:
        print("Skipping speed scatter plot: No models with speed data after NaN drop.")
        return

    fig, ax = plt.subplots(figsize=(SCATTER_FIG_WIDTH, SCATTER_FIG_HEIGHT))
    
    hue_col = None
    palette_to_use = None
    legend_on = False
    
    if label_points:
        # If labeling points, hue by model_id can be too much.
        # Consider not using hue or using a different metric for hue if desired.
        # For now, no hue if all points are labeled to reduce clutter.
        hue_col = None
        palette_to_use = None
        legend_on = False
    elif 'percentage_correct' in df_plot.columns:
        df_plot['correctness_quantile'] = pd.qcut(df_plot['percentage_correct'], NUM_CORRECTNESS_QUANTILES_FOR_HUE, labels=False, duplicates='drop')
        n_colors_needed = df_plot['correctness_quantile'].nunique()
        if n_colors_needed > 0:
            hue_col = 'correctness_quantile'
            palette_to_use = sns.color_palette("coolwarm_r", n_colors=n_colors_needed)
            legend_on = True
        else:
            print("Warning: Could not form quantiles for hue. Using default color.")
            hue_col = None
    
    size_col = 'total_questions' if 'total_questions' in df_plot.columns else None

    sns.scatterplot(
        x='avg_prompt_eval_per_second',
        y='avg_tokens_per_second',
        hue=hue_col,
        palette=palette_to_use,
        size=size_col,
        sizes=(30, 400) if size_col else None,
        data=df_plot,
        ax=ax,
        alpha=0.6, 
        edgecolor='grey',
        linewidth=0.5,
        legend=legend_on 
    )
    
    ax.set_xlabel("Average Prompt Evaluation (tokens/s)")
    ax.set_ylabel("Average Token Generation (tokens/s)")
    ax.grid(True, which="both", ls="-", alpha=0.3)

    if label_points and 'model_id' in df_plot.columns:
        texts = []
        for i in range(df_plot.shape[0]):
            texts.append(ax.text(df_plot['avg_prompt_eval_per_second'].iloc[i],
                                 df_plot['avg_tokens_per_second'].iloc[i],
                                 df_plot['model_id'].iloc[i],
                                 fontdict={'size': SCATTER_LABEL_FONT_SIZE}))
        if texts:
            adjust_text(texts, ax=ax, expand_points=(1.2, 1.2),
                        arrowprops=dict(arrowstyle="-", color='gray', lw=0.5, alpha=0.7))
    
    if legend_on and hue_col == 'correctness_quantile':
        handles, current_labels_from_plot = ax.get_legend_handles_labels()
        
        # Filter handles and labels for the hue ('correctness_quantile') part only
        hue_handles = [h for h, l in zip(handles, current_labels_from_plot) if l in map(str, df_plot['correctness_quantile'].dropna().unique().astype(int))]
        
        unique_quantiles_sorted = sorted(df_plot['correctness_quantile'].dropna().unique())
        
        quantile_labels_map = {}
        if len(unique_quantiles_sorted) == 1:
             quantile_labels_map = {unique_quantiles_sorted[0]: "All (Similar Correctness)"}
        else:
            for i, q in enumerate(unique_quantiles_sorted):
                 quantile_labels_map[q] = f"Q{i+1} ({'Lowest' if i == 0 else ('Highest' if i == len(unique_quantiles_sorted)-1 else str(int(q)))})"

        custom_hue_labels = [quantile_labels_map.get(q_val, f"Quantile {q_val}") for q_val in unique_quantiles_sorted]
        
        # Ensure hue_handles match custom_hue_labels length if possible
        # This simplified legend might not always be perfect if Seaborn's internal legend items are complex
        if custom_hue_handles and len(custom_hue_handles) == len(custom_hue_labels):
            ax.legend(custom_hue_handles, custom_hue_labels, title="Correctness Quantile", bbox_to_anchor=(1.02, 1), loc='upper left')
        elif handles: # Fallback to default legend if customization is problematic
             ax.legend(title="Legend", bbox_to_anchor=(1.02, 1), loc='upper left')


    log_x = apply_log_scale_if_needed(ax, df_plot_for_log['avg_prompt_eval_per_second'], axis='x')
    log_y = apply_log_scale_if_needed(ax, df_plot_for_log['avg_tokens_per_second'], axis='y')

    if log_x: ax.set_xlabel(f"{ax.get_xlabel()} (Log Scale)")
    if log_y: ax.set_ylabel(f"{ax.get_ylabel()} (Log Scale)")
    
    current_xlim = ax.get_xlim()
    current_ylim = ax.get_ylim()
    if not df_plot.empty:
        if not log_x: ax.set_xlim(left=max(0, current_xlim[0] * 0.9 if current_xlim[0] > 0 else 0), right=df_plot['avg_prompt_eval_per_second'].max() * 1.1 if pd.notna(df_plot['avg_prompt_eval_per_second'].max()) else current_xlim[1])
        if not log_y: ax.set_ylim(bottom=max(0, current_ylim[0] * 0.9 if current_ylim[0] > 0 else 0), top=df_plot['avg_tokens_per_second'].max() * 1.1 if pd.notna(df_plot['avg_tokens_per_second'].max()) else current_ylim[1])
    else:
        if not log_x: ax.set_xlim(left=0)
        if not log_y: ax.set_ylim(bottom=0)

    save_plot(fig, "overall_model_speeds_scatter_labeled", "Model Speeds (Prompt vs. Generation)")


def plot_speed_vs_quality(df_model_stats, speed_metric_col, speed_metric_name):
    if df_model_stats.empty:
        print(f"Skipping {speed_metric_name} vs. Quality: No data.")
        return
    
    required_cols = [speed_metric_col, 'percentage_correct', 'model_id', 'total_questions']
    if not all(col in df_model_stats.columns for col in required_cols):
        print(f"Skipping {speed_metric_name} vs. Quality: Missing one of {required_cols}.")
        return

    df_plot = df_model_stats.copy()
    df_plot[speed_metric_col] = pd.to_numeric(df_plot[speed_metric_col], errors='coerce')
    df_plot['percentage_correct'] = pd.to_numeric(df_plot['percentage_correct'], errors='coerce')
    df_plot['total_questions'] = pd.to_numeric(df_plot['total_questions'], errors='coerce').fillna(1)

    df_plot = df_plot.dropna(subset=[speed_metric_col, 'percentage_correct', 'model_id'])
    df_plot_for_log = df_plot[df_plot[speed_metric_col] > 0]
    
    if df_plot.empty:
        print(f"No data for {speed_metric_name} vs Quality after filtering.")
        return

    fig, ax = plt.subplots(figsize=(SCATTER_FIG_WIDTH, SCATTER_FIG_HEIGHT))
    
    # Hue by correctness quantile
    df_plot['correctness_quantile'] = pd.qcut(df_plot['percentage_correct'], NUM_CORRECTNESS_QUANTILES_FOR_HUE, labels=False, duplicates='drop')
    n_colors_needed = df_plot['correctness_quantile'].nunique()
    hue_col_name = None
    palette_to_use_sq = None
    if n_colors_needed > 0:
        hue_col_name = 'correctness_quantile'
        palette_to_use_sq = sns.color_palette("viridis", n_colors=n_colors_needed)
    else:
        print(f"Warning: Could not form quantiles for hue in {speed_metric_name} vs Quality plot.")

    sns.scatterplot(
        x=speed_metric_col,
        y='percentage_correct',
        size='total_questions',
        sizes=(40,400),
        hue=hue_col_name,
        palette=palette_to_use_sq,
        data=df_plot,
        ax=ax,
        alpha=0.6,
        edgecolor='grey',
        linewidth=0.5,
        legend='auto' # Let seaborn decide or set to True/False
    )

    ax.set_xlabel(f"{speed_metric_name} (tokens/s)")
    ax.set_ylabel("Overall Correctness (%)")
    ax.set_ylim(-5, 105)
    ax.grid(True, which="both", ls="-", alpha=0.3)
    
    texts = []
    for i in range(df_plot.shape[0]):
        texts.append(ax.text(df_plot[speed_metric_col].iloc[i],
                             df_plot['percentage_correct'].iloc[i],
                             df_plot['model_id'].iloc[i],
                             fontdict={'size': SCATTER_LABEL_FONT_SIZE}))
    if texts:
        adjust_text(texts, ax=ax, expand_points=(1.1, 1.1), 
                    arrowprops=dict(arrowstyle="-", color='black', lw=0.5, alpha=0.5))
    
    if hue_col_name and ax.get_legend() is not None:
         # Similar legend customization as in plot_overall_model_speeds_scatter if needed
        handles, current_labels_from_plot = ax.get_legend_handles_labels()
        unique_quantiles_sorted = sorted(df_plot['correctness_quantile'].dropna().unique())
        quantile_labels_map = {q: f"Q{i+1}" for i, q in enumerate(unique_quantiles_sorted)}
        
        custom_hue_handles = []
        custom_hue_labels = []
        seen_labels_for_legend = set()

        for i, q_val in enumerate(unique_quantiles_sorted):
            if i < len(palette_to_use_sq): 
                custom_hue_handles.append(plt.Line2D([0], [0], marker='o', color='w', 
                                                     markerfacecolor=palette_to_use_sq[i], markersize=8))
                custom_hue_labels.append(quantile_labels_map.get(q_val, f"Quantile {q_val}"))
        
        if custom_hue_handles:
            ax.legend(custom_hue_handles, custom_hue_labels, title="Correctness Quantile", bbox_to_anchor=(1.02, 1), loc='upper left')
            # You might need to add the size legend back if it gets removed:
            # size_legend = sns.scatterplot(...) # get the scatter plot artist
            # handles, labels = size_legend.get_legend_handles_labels()
            # ax.legend(handles_for_size, labels_for_size, title="Total Questions", bbox_to_anchor=(1.02, 0.5), loc='center left')


    if apply_log_scale_if_needed(ax, df_plot_for_log[speed_metric_col], axis='x'):
        ax.set_xlabel(f"{ax.get_xlabel()} (Log Scale)")
    else:
        current_xlim = ax.get_xlim()
        if not df_plot_for_log.empty:
            ax.set_xlim(left=max(0, current_xlim[0] * 0.8 if current_xlim[0] > 0 else 0), right=df_plot_for_log[speed_metric_col].max() * 1.15 if pd.notna(df_plot_for_log[speed_metric_col].max()) else current_xlim[1])
        elif not df_plot.empty:
            ax.set_xlim(left=df_plot[speed_metric_col].min() -1 , right=df_plot[speed_metric_col].max() + 1 )
        else:
            ax.set_xlim(left=0)

    save_plot(fig, f"{speed_metric_col.replace('_','-')}_vs_quality_labeled", f"{speed_metric_name} vs. Overall Correctness")

def plot_category_correctness_heatmap(df_runs, df_model_stats, top_m_models=30):
    if df_runs.empty or df_model_stats.empty:
        print("Skipping category correctness heatmap: Missing benchmark_runs or model_stats data.")
        return
    if 'category' not in df_runs.columns or 'model_id' not in df_runs.columns or 'correct' not in df_runs.columns:
        print("Skipping category correctness heatmap: Missing required columns (category, model_id, correct in df_runs).")
        return
    if 'model_id' not in df_model_stats.columns or 'percentage_correct' not in df_model_stats.columns:
        print("Skipping category correctness heatmap: Missing required columns (model_id, percentage_correct in df_model_stats).")
        return

    df_overall_correctness = df_model_stats[['model_id', 'percentage_correct']].copy()
    df_overall_correctness.rename(columns={'percentage_correct': 'Overall'}, inplace=True)
    
    top_overall_models_df = df_overall_correctness.sort_values('Overall', ascending=False).head(top_m_models)
    top_model_ids_ordered = top_overall_models_df['model_id'].tolist()

    df_runs['correct'] = pd.to_numeric(df_runs['correct'], errors='coerce').fillna(0)
    
    df_filtered_runs = df_runs[df_runs['model_id'].isin(top_model_ids_ordered) & df_runs['category'].notna()]
    
    if df_filtered_runs.empty:
        print("No category data for top models to plot heatmap. Attempting to show overall only.")
        if not top_overall_models_df.empty:
            heatmap_data_final = top_overall_models_df.set_index('model_id')
            heatmap_data_final = heatmap_data_final.reindex(top_model_ids_ordered)
        else:
            print("No model data available at all for heatmap.")
            return
    else:
        category_agg = df_filtered_runs.groupby(['category', 'model_id']).agg(
            correct_answers=('correct', 'sum'),
            total_questions=('correct', 'count')
        ).reset_index()
        
        category_agg = category_agg[category_agg['total_questions'] >= MIN_QUESTIONS_FOR_CATEGORY_PLOT]
        if category_agg.empty:
            print(f"No category data meets MIN_QUESTIONS_FOR_CATEGORY_PLOT={MIN_QUESTIONS_FOR_CATEGORY_PLOT} for heatmap. Showing overall only.")
            heatmap_data_final = top_overall_models_df.set_index('model_id')
            heatmap_data_final = heatmap_data_final.reindex(top_model_ids_ordered)
        else:
            category_agg['percentage_correct'] = (category_agg['correct_answers'] / category_agg['total_questions']) * 100
            category_heatmap_data = category_agg.pivot(index='model_id', columns='category', values='percentage_correct')
            heatmap_data_final = pd.merge(top_overall_models_df.set_index('model_id'), 
                                          category_heatmap_data, 
                                          on='model_id', 
                                          how='left')
            cols = heatmap_data_final.columns.tolist()
            if 'Overall' in cols:
                cols.remove('Overall')
                category_cols_sorted = sorted([col for col in cols if col != 'Overall'])
                final_cols_order = ['Overall'] + category_cols_sorted
                heatmap_data_final = heatmap_data_final[final_cols_order]
            heatmap_data_final = heatmap_data_final.reindex(top_model_ids_ordered)

    heatmap_data_final = heatmap_data_final.dropna(how='all', axis=0).dropna(how='all', axis=1)

    if heatmap_data_final.empty:
        print("Pivoted heatmap data is empty after filtering and merging.")
        return

    num_categories_plot = len(heatmap_data_final.columns)
    num_models_hm_plot = len(heatmap_data_final.index)
    
    fig_width = max(FIG_BASE_WIDTH, num_categories_plot * 1.0) 
    fig_height = max(FIG_MIN_HEIGHT, num_models_hm_plot * 0.45)

    fig, ax = plt.subplots(figsize=(fig_width, fig_height))
    cmap = sns.diverging_palette(10, 240, as_cmap=True, center="light")

    sns.heatmap(heatmap_data_final, annot=True, fmt=".1f", cmap=cmap, linewidths=.5, ax=ax, 
                cbar=True, center=50, vmin=0, vmax=100, annot_kws={"size": ANNOTATION_FONT_SIZE - 1})
    plt.xticks(rotation=45, ha='right')
    plt.yticks(rotation=0)
    ax.set_xlabel("Category / Overall")
    ax.set_ylabel("Model ID (Sorted by Overall Performance)")
    
    save_plot(fig, f"category_correctness_heatmap_with_overall_top_{top_m_models}_models", 
              f"Correctness by Category & Overall (Top {len(heatmap_data_final.index)} Models, Cats >= {MIN_QUESTIONS_FOR_CATEGORY_PLOT} Qs)")

def plot_top_models_for_specific_categories(df_runs, categories_to_plot, df_model_stats):
    if df_runs.empty: return
    if 'category' not in df_runs.columns or 'model_id' not in df_runs.columns or 'correct' not in df_runs.columns:
        return

    df_runs['correct'] = pd.to_numeric(df_runs['correct'], errors='coerce').fillna(0)

    for category in categories_to_plot:
        df_category_data = df_runs[df_runs['category'] == category]
        if df_category_data.empty:
            print(f"No data for category: {category}")
            continue

        model_agg = df_category_data.groupby('model_id').agg(
            correct_answers=('correct', 'sum'),
            total_questions=('correct', 'count')
        ).reset_index()
        
        model_agg = model_agg[model_agg['total_questions'] >= MIN_QUESTIONS_FOR_CATEGORY_PLOT]
        if model_agg.empty:
            print(f"Not enough model data (after MIN_QUESTIONS_FOR_CATEGORY_PLOT) for category '{category}'.")
            continue

        model_agg['percentage_correct'] = (model_agg['correct_answers'] / model_agg['total_questions']) * 100
        
        df_sorted = model_agg.sort_values('percentage_correct', ascending=False).head(TOP_N_MODELS_PER_CATEGORY)
        
        if df_sorted.empty:
            print(f"Not enough model data for category '{category}' after sorting and taking top N.")
            continue

        num_models_plot = len(df_sorted)
        fig_height = max(FIG_MIN_HEIGHT, num_models_plot * FIG_HEIGHT_PER_ITEM_BAR)
        fig_width = FIG_BASE_WIDTH

        fig, ax = plt.subplots(figsize=(fig_width, fig_height))
        bars = sns.barplot(x='percentage_correct', y='model_id', data=df_sorted, ax=ax, 
                           palette="mako", hue='model_id', dodge=False, legend=False,
                           order=df_sorted['model_id'].tolist())
                           
        ax.set_xlabel("Correctness (%)")
        ax.set_ylabel("Model ID")
        ax.set_xlim(0, 105)
        ax.grid(True, axis='x', linestyle='--', alpha=0.7)

        for i, (idx, row) in enumerate(df_sorted.iterrows()):
            if i < len(bars.patches):
                bar = bars.patches[i]
                percentage = row['percentage_correct']
                correct_ans = int(row['correct_answers'])
                total_q = int(row['total_questions'])
                
                label_text = f"{percentage:.1f}% ({correct_ans}/{total_q})"
                ax.text(bar.get_width() + 0.5, bar.get_y() + bar.get_height() / 2,
                        label_text, va='center', ha='left', fontsize=ANNOTATION_FONT_SIZE)
            else:
                print(f"Warning: Mismatch between number of bars and rows for category {category}")

        filename_cat = "".join(c if c.isalnum() or c in (' ', '_', '-') else '_' for c in category).replace(' ', '_')
        save_plot(fig, f"category_{filename_cat}_top_models_bar", f"Top Models for Category: {category}")

def main():
    clear_output_directory(OUTPUT_DIR)

    print(f"Connecting to database: {DB_PATH}")
    if not os.path.exists(DB_PATH):
        print(f"Error: Database file '{DB_PATH}' not found.")
        return

    conn = sqlite3.connect(DB_PATH)
    try:
        df_model_stats = pd.read_sql_query("SELECT * FROM model_stats", conn)
        df_benchmark_runs = pd.read_sql_query("SELECT * FROM benchmark_runs", conn)
    except pd.io.sql.DatabaseError as e:
        print(f"Error reading from database: {e}")
        conn.close()
        return
    finally:
        conn.close()

    if 'percentage_correct' in df_model_stats.columns:
         df_model_stats['percentage_correct'] = pd.to_numeric(df_model_stats['percentage_correct'], errors='coerce').fillna(0)
    elif 'correct_answers' in df_model_stats.columns and 'total_questions' in df_model_stats.columns:
        df_model_stats['correct_answers'] = pd.to_numeric(df_model_stats['correct_answers'], errors='coerce').fillna(0)
        df_model_stats['total_questions'] = pd.to_numeric(df_model_stats['total_questions'], errors='coerce').fillna(0)
        df_model_stats['percentage_correct'] = np.where(
            df_model_stats['total_questions'] > 0,
            (df_model_stats['correct_answers'] / df_model_stats['total_questions']) * 100,
            0
        ).round(1)
    else:
        print("Warning: Cannot calculate 'percentage_correct' for model_stats. Required columns missing.")


    print(f"\n--- Generating Overall Model Plots (from model_stats) ---")
    if not df_model_stats.empty:
        plot_overall_model_correctness_bar(df_model_stats.copy())
        plot_overall_model_speeds_scatter(df_model_stats.copy(), label_points=True) 
        
        plot_speed_vs_quality(df_model_stats.copy(), 'avg_prompt_eval_per_second', 'Prompt Eval Speed')
        plot_speed_vs_quality(df_model_stats.copy(), 'avg_tokens_per_second', 'Token Generation Speed')
    else:
        print("model_stats table is empty or not found. Skipping overall plots.")

    print(f"\n--- Generating Category Plots (from benchmark_runs) ---")
    if not df_benchmark_runs.empty and not df_model_stats.empty:
        if 'category' in df_benchmark_runs.columns:
            df_benchmark_runs['category'] = df_benchmark_runs['category'].fillna('Uncategorized').astype(str)
            
            plot_category_correctness_heatmap(df_benchmark_runs.copy(), df_model_stats.copy(), top_m_models=TOP_N_MODELS_OVERALL)
            
            selected_main_categories_for_bar = ['Computer Science', 'Drupal', 'History', 'Math', 'Logic', 'Science', 'Language', 'Literature', 'Philosophy', 'Music', 'Culture', 'Economics', 'Geography'] 
            available_categories = df_benchmark_runs['category'].unique()
            categories_to_plot_bars = [cat for cat in selected_main_categories_for_bar if cat in available_categories]
            if categories_to_plot_bars:
                 plot_top_models_for_specific_categories(df_benchmark_runs.copy(), categories_to_plot_bars, df_model_stats.copy())
            else:
                print("None of the selected_main_categories_for_bar exist in the data.")
        else:
            print("Warning: 'category' column not found in benchmark_runs. Skipping category plots.")
    else:
        print("benchmark_runs or model_stats table is empty. Skipping category plots.")

    print(f"\nAll plots saved to '{OUTPUT_DIR}' directory.")

if __name__ == "__main__":
    plt.rcParams['figure.constrained_layout.use'] = False
    main()