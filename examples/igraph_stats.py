#!/usr/bin/env python3
import tkinter as tk
from tkinter import ttk, filedialog, messagebox
import sqlite3
import pandas as pd
import matplotlib.pyplot as plt
import matplotlib.colors as mcolors
import seaborn as sns
import os
import shutil
import numpy as np
from matplotlib.ticker import LogLocator, NullFormatter, ScalarFormatter
from matplotlib.backends.backend_tkagg import FigureCanvasTkAgg, NavigationToolbar2Tk
from adjustText import adjust_text

# --- Configuration ---
# ... (same as before)
DB_PATH = 'benchmark.db'
OUTPUT_DIR = 'benchmark_graphs'
TOP_N_MODELS_OVERALL = 25
TOP_N_MODELS_PER_CATEGORY = 15
NUM_CORRECTNESS_QUANTILES_FOR_HUE = 5
MIN_QUESTIONS_FOR_CATEGORY_PLOT = 1

LOG_SCALE_THRESHOLD_RATIO = 20
FIG_BASE_WIDTH = 12
FIG_HEIGHT_PER_ITEM_BAR = 0.35
FIG_MIN_HEIGHT = 7
FIG_MAX_WIDTH_BAR = 25
SCATTER_FIG_WIDTH = 18
SCATTER_FIG_HEIGHT = 15

sns.set_theme(style="whitegrid", palette="muted")
plt.rcParams.update({
    'font.size': 10, 'axes.titlesize': 14, 'axes.labelsize': 12,
    'xtick.labelsize': 9, 'ytick.labelsize': 10, 'legend.fontsize': 9,
    'figure.titlesize': 15, 'figure.dpi': 100,
    'lines.linewidth': 1.8, 'lines.markersize': 5,
})
ANNOTATION_FONT_SIZE = 7
SCATTER_LABEL_FONT_SIZE = 6.5


# Global variables for data and Tkinter widgets
df_model_stats_global = pd.DataFrame()
df_benchmark_runs_global = pd.DataFrame()
all_model_names_global = []
all_categories_global = []
db_path_global = ""

# --- Utility Functions ---
def clear_output_directory(directory):
    # ... (same)
    if not os.path.exists(directory):
        os.makedirs(directory, exist_ok=True)
        print(f"Created output directory: {directory}")
        return
    
    for item in os.listdir(directory):
        item_path = os.path.join(directory, item)
        if os.path.isfile(item_path) or os.path.islink(item_path):
            os.unlink(item_path)
        elif os.path.isdir(item_path):
            shutil.rmtree(item_path)
    print(f"Cleared output directory: {directory}")

def save_or_show_plot(figure, canvas_frame, toolbar_frame, filename_base_for_save, title_for_save, show_inline=True):
    # ... (same)
    figure.suptitle(title_for_save, fontsize=plt.rcParams['figure.titlesize'])
    try:
        plt.tight_layout(rect=[0, 0.03, 1, 0.95])
    except UserWarning as e:
        print(f"Warning during tight_layout for {filename_base_for_save}: {e}")
    
    if show_inline and canvas_frame:
        clear_plot_canvas(canvas_frame, toolbar_frame)
        canvas = FigureCanvasTkAgg(figure, master=canvas_frame)
        canvas.draw()
        canvas.get_tk_widget().pack(side=tk.TOP, fill=tk.BOTH, expand=True)

        if toolbar_frame:
            for widget in toolbar_frame.winfo_children():
                widget.destroy()
            toolbar = NavigationToolbar2Tk(canvas, toolbar_frame)
            toolbar.update()
            # toolbar_frame.pack(side=tk.BOTTOM, fill=tk.X) 

    else: 
        if not os.path.exists(OUTPUT_DIR):
            os.makedirs(OUTPUT_DIR)
        filepath = os.path.join(OUTPUT_DIR, f"{filename_base_for_save}.png")
        plt.savefig(filepath)
        print(f"Saved: {filepath}")
    
    plt.close(figure)

def clear_plot_canvas(canvas_widget, toolbar_widget):
    # ... (same)
    if canvas_widget:
        for widget in canvas_widget.winfo_children():
            widget.destroy()
    if toolbar_widget:
        for widget in toolbar_widget.winfo_children():
            widget.destroy()

def apply_log_scale_if_needed(ax, series_data, axis='y'):
    # ... (same)
    numeric_series = pd.to_numeric(series_data, errors='coerce')
    positive_data = numeric_series[numeric_series > 0]
    
    if not positive_data.empty:
        min_val = positive_data.min()
        max_val = positive_data.max()
        if max_val > 0 and min_val > 0 and (max_val / min_val) > LOG_SCALE_THRESHOLD_RATIO :
            if axis == 'y':
                ax.set_yscale('log'); ax.yaxis.set_major_formatter(ScalarFormatter()); ax.yaxis.set_minor_formatter(NullFormatter())
            elif axis == 'x':
                ax.set_xscale('log'); ax.xaxis.set_major_formatter(ScalarFormatter()); ax.xaxis.set_minor_formatter(NullFormatter())
            print(f"Applied log scale to {axis}-axis for plot (min_pos={min_val:.2f}, max={max_val:.2f})")
            return True
    return False

# --- Plotting Functions (definitions are the same as before, ensure they use the global constants correctly) ---
def plot_overall_model_correctness_bar(df_model_stats, canvas_frame, toolbar_frame, top_n=TOP_N_MODELS_OVERALL, selected_models=None):
    # ... (implementation from previous version) ...
    if df_model_stats.empty or 'percentage_correct' not in df_model_stats.columns:
        messagebox.showerror("Data Error", "Model stats data is empty or missing 'percentage_correct'.")
        return

    df_to_plot = df_model_stats.copy()
    plot_title_suffix = f"Top {top_n} Models"

    if selected_models:
        df_to_plot = df_to_plot[df_to_plot['model_id'].isin(selected_models)]
        plot_title_suffix = f"Selected Models ({len(df_to_plot)})"
        if df_to_plot.empty:
            messagebox.showinfo("No Data", "None of the selected models found in the data.")
            return
    
    df_sorted = df_to_plot.sort_values('percentage_correct', ascending=False)
    
    if not selected_models:
        df_sorted = df_sorted.head(top_n)
    
    if df_sorted.empty:
        messagebox.showinfo("No Data", f"No models found after filtering/top_n for correctness bar.")
        return

    num_models_plot = len(df_sorted)
    fig_height = max(FIG_MIN_HEIGHT, num_models_plot * FIG_HEIGHT_PER_ITEM_BAR)
    fig_width = FIG_BASE_WIDTH

    fig, ax = plt.subplots(figsize=(fig_width, fig_height))
    bars = sns.barplot(x='percentage_correct', y='model_id', data=df_sorted, ax=ax, 
                       palette="viridis_r", hue='model_id', dodge=False, legend=False,
                       order=df_sorted['model_id'].tolist())
                       
    ax.set_xlabel("Correctness (%)", fontsize=plt.rcParams['axes.labelsize'])
    ax.set_ylabel("Model ID", fontsize=plt.rcParams['axes.labelsize'])
    ax.set_xlim(0, 105)
    ax.grid(True, axis='x', linestyle='--', alpha=0.7)

    for i, (index, row) in enumerate(df_sorted.iterrows()):
        if i < len(bars.patches):
            bar = bars.patches[i]
            percentage = row['percentage_correct']
            label_text = f"{percentage:.1f}%"
            ax.text(bar.get_width() + 0.5, bar.get_y() + bar.get_height() / 2,
                    label_text, va='center', ha='left', fontsize=ANNOTATION_FONT_SIZE)
    
    title = f"{plot_title_suffix} by Correctness"
    save_or_show_plot(fig, canvas_frame, toolbar_frame, "overall_model_correctness_bar", title, show_inline=True)

def plot_overall_model_speeds_scatter(df_model_stats, canvas_frame, toolbar_frame, label_points=True, selected_models=None):
    # ... (implementation from previous version, ensure SCATTER_FIG_WIDTH/HEIGHT are used) ...
    if df_model_stats.empty:
        messagebox.showerror("Data Error", "Model stats data is empty for speed scatter.")
        return

    df_speeds = df_model_stats.copy()
    speed_cols_check = ['avg_prompt_eval_per_second', 'avg_tokens_per_second', 'model_id']
    if 'percentage_correct' in df_speeds.columns: speed_cols_check.append('percentage_correct')
    if 'total_questions' in df_speeds.columns:  speed_cols_check.append('total_questions')
        
    for col in speed_cols_check:
        if col not in df_speeds.columns:
            if col in ['avg_prompt_eval_per_second', 'avg_tokens_per_second', 'model_id']:
                 messagebox.showerror("Data Error", f"Critical Column '{col}' not found for speed scatter.")
                 return
        elif col not in ['model_id']:
             df_speeds[col] = pd.to_numeric(df_speeds[col], errors='coerce')

    df_plot_base = df_speeds.dropna(subset=['avg_prompt_eval_per_second', 'avg_tokens_per_second'])
    
    if selected_models:
        df_plot = df_plot_base[df_plot_base['model_id'].isin(selected_models)].copy()
    else:
        df_plot = df_plot_base.copy()

    df_plot_for_log = df_plot[(df_plot['avg_prompt_eval_per_second'] > 0) & (df_plot['avg_tokens_per_second'] > 0)].copy()
    
    if df_plot.empty:
        messagebox.showinfo("No Data", "No models with required speed data to plot (or after filtering).")
        return

    fig, ax = plt.subplots(figsize=(SCATTER_FIG_WIDTH * 0.8, SCATTER_FIG_HEIGHT * 0.8))
    
    hue_col = None; palette_to_use = None; legend_on = False
    if label_points:
        hue_col = None; palette_to_use = None; legend_on = False 
    elif 'percentage_correct' in df_plot.columns and not selected_models:
        df_plot['correctness_quantile'] = pd.qcut(df_plot['percentage_correct'], NUM_CORRECTNESS_QUANTILES_FOR_HUE, labels=False, duplicates='drop')
        n_colors_needed = df_plot['correctness_quantile'].nunique()
        if n_colors_needed > 0:
            hue_col = 'correctness_quantile'; palette_to_use = sns.color_palette("coolwarm_r", n_colors=n_colors_needed); legend_on = True
        else: print("Warning: Could not form quantiles for hue.")

    size_col = 'total_questions' if 'total_questions' in df_plot.columns and pd.api.types.is_numeric_dtype(df_plot['total_questions']) else None

    sns.scatterplot(
        x='avg_prompt_eval_per_second', y='avg_tokens_per_second', hue=hue_col, palette=palette_to_use,
        size=size_col, sizes=(30, 400) if size_col else None,
        data=df_plot, ax=ax, alpha=0.6, edgecolor='grey', linewidth=0.5, legend=legend_on
    )
    
    ax.set_xlabel("Avg Prompt Eval (tokens/s)", fontsize=plt.rcParams['axes.labelsize'])
    ax.set_ylabel("Avg Token Gen (tokens/s)", fontsize=plt.rcParams['axes.labelsize'])
    ax.grid(True, which="both", ls="-", alpha=0.3)

    if label_points and 'model_id' in df_plot.columns:
        texts = [ax.text(df_plot['avg_prompt_eval_per_second'].iloc[i], 
                         df_plot['avg_tokens_per_second'].iloc[i], 
                         df_plot['model_id'].iloc[i], fontsize=SCATTER_LABEL_FONT_SIZE) 
                 for i in range(df_plot.shape[0])]
        if texts: adjust_text(texts, ax=ax, expand_points=(1.2, 1.2),
                               arrowprops=dict(arrowstyle="-", color='gray', lw=0.5, alpha=0.7))
        if legend_on and ax.get_legend() is not None: ax.get_legend().remove()

    elif legend_on and hue_col == 'correctness_quantile':
        handles, _ = ax.get_legend_handles_labels()
        unique_quantiles_sorted = sorted(df_plot['correctness_quantile'].dropna().unique().astype(int))
        quantile_labels_map = {q: f"Q{i+1}" for i, q in enumerate(unique_quantiles_sorted)}
        custom_hue_labels = [quantile_labels_map.get(q_val, f"Q {q_val}") for q_val in unique_quantiles_sorted]
        
        current_palette = palette_to_use if palette_to_use and len(palette_to_use) >= len(unique_quantiles_sorted) else sns.color_palette("coolwarm_r", n_colors=len(unique_quantiles_sorted))
        custom_hue_handles = [plt.Line2D([0], [0], marker='o', color='w', 
                                          markerfacecolor=current_palette[i % len(current_palette)], markersize=8)
                              for i in range(len(unique_quantiles_sorted))]
        if custom_hue_handles:
            if ax.get_legend() is not None: ax.get_legend().remove()
            ax.legend(custom_hue_handles, custom_hue_labels, title="Correctness Quantile", bbox_to_anchor=(1.02, 1), loc='upper left')

    log_x = apply_log_scale_if_needed(ax, df_plot_for_log['avg_prompt_eval_per_second'], axis='x')
    log_y = apply_log_scale_if_needed(ax, df_plot_for_log['avg_tokens_per_second'], axis='y')
    if log_x: ax.set_xlabel(f"{ax.get_xlabel()} (Log Scale)")
    if log_y: ax.set_ylabel(f"{ax.get_ylabel()} (Log Scale)")
    
    current_xlim = ax.get_xlim(); current_ylim = ax.get_ylim()
    if not df_plot.empty:
        max_prompt_speed = df_plot['avg_prompt_eval_per_second'].max()
        max_token_speed = df_plot['avg_tokens_per_second'].max()
        min_prompt_speed = df_plot['avg_prompt_eval_per_second'].min()
        min_token_speed = df_plot['avg_tokens_per_second'].min()

        if not log_x: ax.set_xlim(left=max(0, min_prompt_speed * 0.9 if pd.notna(min_prompt_speed) and min_prompt_speed > 0 else 0), 
                                 right=max_prompt_speed * 1.1 if pd.notna(max_prompt_speed) else current_xlim[1])
        if not log_y: ax.set_ylim(bottom=max(0, min_token_speed * 0.9 if pd.notna(min_token_speed) and min_token_speed > 0 else 0), 
                                 top=max_token_speed * 1.1 if pd.notna(max_token_speed) else current_ylim[1])
    
    title = "Model Speeds (Prompt vs. Generation)"
    if selected_models: title += f" (Filtered: {len(selected_models)} models)"
    save_or_show_plot(fig, canvas_frame, toolbar_frame, "overall_model_speeds_scatter_labeled", title, show_inline=True)

def plot_speed_vs_quality(df_model_stats, speed_metric_col, speed_metric_name, canvas_frame, toolbar_frame, selected_models=None):
    # ... (implementation from previous version, ensure SCATTER_FIG_WIDTH/HEIGHT are used) ...
    if df_model_stats.empty:
        messagebox.showerror("Data Error", f"Model stats data is empty for {speed_metric_name} vs. Quality.")
        return
    required_cols = [speed_metric_col, 'percentage_correct', 'model_id', 'total_questions']
    if not all(col in df_model_stats.columns for col in required_cols):
        messagebox.showerror("Data Error", f"Skipping {speed_metric_name} vs. Quality: Missing one of {required_cols}.")
        return

    df_plot_base = df_model_stats.copy()
    df_plot_base[speed_metric_col] = pd.to_numeric(df_plot_base[speed_metric_col], errors='coerce')
    df_plot_base['percentage_correct'] = pd.to_numeric(df_plot_base['percentage_correct'], errors='coerce')
    df_plot_base['total_questions'] = pd.to_numeric(df_plot_base['total_questions'], errors='coerce').fillna(1)

    df_plot = df_plot_base.dropna(subset=[speed_metric_col, 'percentage_correct', 'model_id'])
    if selected_models:
        df_plot = df_plot[df_plot['model_id'].isin(selected_models)].copy()
        
    df_plot_for_log = df_plot[df_plot[speed_metric_col] > 0]
    
    if df_plot.empty:
        messagebox.showinfo("No Data", f"No data for {speed_metric_name} vs Quality after filtering.")
        return

    fig, ax = plt.subplots(figsize=(SCATTER_FIG_WIDTH * 0.8, SCATTER_FIG_HEIGHT * 0.8))
    
    hue_col = None; palette_to_use_sq = None; legend_on = False
    if not selected_models and 'percentage_correct' in df_plot.columns:
        df_plot['correctness_quantile_hue'] = pd.qcut(df_plot['percentage_correct'], NUM_CORRECTNESS_QUANTILES_FOR_HUE, labels=False, duplicates='drop')
        n_colors_needed = df_plot['correctness_quantile_hue'].nunique()
        if n_colors_needed > 0:
            hue_col = 'correctness_quantile_hue'
            palette_to_use_sq = sns.color_palette("viridis", n_colors=n_colors_needed)
            legend_on = True

    size_col = 'total_questions' if 'total_questions' in df_plot.columns and pd.api.types.is_numeric_dtype(df_plot['total_questions']) else None

    sns.scatterplot(
        x=speed_metric_col, y='percentage_correct', size=size_col, sizes=(40,400) if size_col else None,
        hue=hue_col, palette=palette_to_use_sq, data=df_plot, ax=ax, alpha=0.7, 
        edgecolor='grey', linewidth=0.5, legend=legend_on
    )

    ax.set_xlabel(f"{speed_metric_name} (tokens/s)", fontsize=plt.rcParams['axes.labelsize'])
    ax.set_ylabel("Overall Correctness (%)", fontsize=plt.rcParams['axes.labelsize'])
    ax.set_ylim(-5, 105)
    ax.grid(True, which="both", ls="-", alpha=0.3)
    
    texts = [ax.text(df_plot[speed_metric_col].iloc[i], df_plot['percentage_correct'].iloc[i],
                     df_plot['model_id'].iloc[i], fontdict={'size': SCATTER_LABEL_FONT_SIZE}) 
             for i in range(df_plot.shape[0])]
    if texts: adjust_text(texts, ax=ax, expand_points=(1.1, 1.1), arrowprops=dict(arrowstyle="-", color='black', lw=0.5, alpha=0.5))
    
    if legend_on and ax.get_legend() is not None: # If labels are ON, remove default legend from hue
        ax.get_legend().remove()
    # Custom legend for quantiles if hue was used for it
    if legend_on and hue_col == 'correctness_quantile_hue':
        handles, _ = ax.get_legend_handles_labels() # Re-get handles if any were made by scatter
        unique_quantiles_sorted = sorted(df_plot['correctness_quantile_hue'].dropna().unique().astype(int))
        quantile_labels_map = {q: f"Q{i+1}" for i, q in enumerate(unique_quantiles_sorted)}
        custom_hue_labels = [quantile_labels_map.get(q_val, f"Q {q_val}") for q_val in unique_quantiles_sorted]
        
        current_palette = palette_to_use_sq if palette_to_use_sq and len(palette_to_use_sq) >= len(unique_quantiles_sorted) else sns.color_palette("viridis", n_colors=len(unique_quantiles_sorted))
        custom_hue_handles = [plt.Line2D([0], [0], marker='o', color='w', markerfacecolor=current_palette[i % len(current_palette)], markersize=8) for i in range(len(unique_quantiles_sorted))]
        
        if custom_hue_handles:
            ax.legend(custom_hue_handles, custom_hue_labels, title="Correctness Quantile", bbox_to_anchor=(1.02, 1), loc='upper left')


    if apply_log_scale_if_needed(ax, df_plot_for_log[speed_metric_col], axis='x'):
        ax.set_xlabel(f"{ax.get_xlabel()} (Log Scale)")
    else:
        current_xlim = ax.get_xlim()
        if not df_plot_for_log.empty:
            min_speed = df_plot_for_log[speed_metric_col].min(); max_speed = df_plot_for_log[speed_metric_col].max()
            ax.set_xlim(left=max(0, min_speed * 0.8 if pd.notna(min_speed) and min_speed > 0 else 0), 
                        right=max_speed * 1.15 if pd.notna(max_speed) else current_xlim[1])
        elif not df_plot.empty:
            min_speed = df_plot[speed_metric_col].min(); max_speed = df_plot[speed_metric_col].max()
            ax.set_xlim(left=min_speed -1 if pd.notna(min_speed) else 0 , 
                        right=max_speed + 1 if pd.notna(max_speed) else 10)
        else: ax.set_xlim(left=0)
            
    title = f"{speed_metric_name} vs. Overall Correctness"
    if selected_models: title += f" (Filtered: {len(selected_models)} models)"
    save_or_show_plot(fig, canvas_frame, toolbar_frame, f"{speed_metric_col.replace('_','-')}_vs_quality_labeled", title, show_inline=True)


def plot_category_correctness_heatmap(df_runs, df_model_stats, canvas_frame, toolbar_frame, top_m_models=30, selected_models=None):
    # ... (implementation from previous version, ensure FIG_MIN_HEIGHT and FIG_BASE_WIDTH are used correctly) ...
    if df_runs.empty or df_model_stats.empty:
        messagebox.showerror("Data Error", "Heatmap: Missing benchmark_runs or model_stats data.")
        return
    if 'category' not in df_runs.columns or 'model_id' not in df_runs.columns or 'correct' not in df_runs.columns:
        messagebox.showerror("Data Error", "Heatmap: Missing required columns in df_runs.")
        return
    if 'model_id' not in df_model_stats.columns or 'percentage_correct' not in df_model_stats.columns:
        messagebox.showerror("Data Error", "Heatmap: Missing required columns in df_model_stats.")
        return

    df_overall_correctness = df_model_stats[['model_id', 'percentage_correct']].copy()
    df_overall_correctness.rename(columns={'percentage_correct': 'Overall'}, inplace=True)
    
    title_suffix_models = f"Top {top_m_models} Overall"
    if selected_models:
        df_overall_correctness_filtered = df_overall_correctness[df_overall_correctness['model_id'].isin(selected_models)]
        top_overall_models_df = df_overall_correctness_filtered.sort_values('Overall', ascending=False)
        title_suffix_models = f"Selected ({len(top_overall_models_df)})"
        if top_overall_models_df.empty:
            messagebox.showinfo("No Data", "None of the selected models found for heatmap.")
            return
    else:
        top_overall_models_df = df_overall_correctness.sort_values('Overall', ascending=False).head(top_m_models)

    top_model_ids_ordered = top_overall_models_df['model_id'].tolist()
    
    df_runs['correct'] = pd.to_numeric(df_runs['correct'], errors='coerce').fillna(0)
    df_filtered_runs = df_runs[df_runs['model_id'].isin(top_model_ids_ordered) & df_runs['category'].notna()]
    
    heatmap_data_final = pd.DataFrame() 

    if df_filtered_runs.empty:
        if not top_overall_models_df.empty:
            heatmap_data_final = top_overall_models_df.set_index('model_id')
            heatmap_data_final = heatmap_data_final.reindex(top_model_ids_ordered)
        else: messagebox.showinfo("No Data", "No model data for heatmap."); return
    else:
        category_agg = df_filtered_runs.groupby(['category', 'model_id']).agg(
            correct_answers=('correct', 'sum'), total_questions=('correct', 'count')
        ).reset_index()
        category_agg = category_agg[category_agg['total_questions'] >= MIN_QUESTIONS_FOR_CATEGORY_PLOT]
        if category_agg.empty:
            if not top_overall_models_df.empty:
                heatmap_data_final = top_overall_models_df.set_index('model_id')
                heatmap_data_final = heatmap_data_final.reindex(top_model_ids_ordered)
            else: messagebox.showinfo("No Data", f"No cat data meets min Qs, and no overall data for heatmap."); return
        else:
            category_agg['percentage_correct'] = (category_agg['correct_answers'] / category_agg['total_questions']) * 100
            category_heatmap_data = category_agg.pivot(index='model_id', columns='category', values='percentage_correct')
            heatmap_data_final = pd.merge(top_overall_models_df.set_index('model_id'), 
                                          category_heatmap_data, on='model_id', how='left')
            cols = heatmap_data_final.columns.tolist()
            if 'Overall' in cols:
                cols.remove('Overall')
                category_cols_sorted = sorted([col for col in cols if col != 'Overall'])
                final_cols_order = ['Overall'] + category_cols_sorted
                heatmap_data_final = heatmap_data_final[final_cols_order]
            heatmap_data_final = heatmap_data_final.reindex(top_model_ids_ordered)

    heatmap_data_final = heatmap_data_final.dropna(how='all', axis=0).dropna(how='all', axis=1)
    if heatmap_data_final.empty: messagebox.showinfo("No Data", "Pivoted heatmap data empty."); return

    num_categories_plot = len(heatmap_data_final.columns)
    num_models_hm_plot = len(heatmap_data_final.index)
    
    fig_width = max(FIG_BASE_WIDTH, num_categories_plot * 1.0) 
    fig_height = max(FIG_MIN_HEIGHT, num_models_hm_plot * 0.45)

    fig, ax = plt.subplots(figsize=(fig_width, fig_height))
    cmap = sns.diverging_palette(10, 240, as_cmap=True, center="light")
    sns.heatmap(heatmap_data_final, annot=True, fmt=".1f", cmap=cmap, linewidths=.5, ax=ax, 
                cbar=True, center=50, vmin=0, vmax=100, annot_kws={"size": ANNOTATION_FONT_SIZE - 2})
    plt.xticks(rotation=45, ha='right'); plt.yticks(rotation=0)
    ax.set_xlabel("Category / Overall", fontsize=plt.rcParams['axes.labelsize'])
    ax.set_ylabel(f"Model ID ({title_suffix_models})", fontsize=plt.rcParams['axes.labelsize'])
    
    title = f"Correctness by Category & Overall ({title_suffix_models}, Cats >= {MIN_QUESTIONS_FOR_CATEGORY_PLOT} Qs)"
    save_or_show_plot(fig, canvas_frame, toolbar_frame, "category_correctness_heatmap_with_overall", title, show_inline=True)


def plot_top_models_for_specific_categories(df_runs, categories_to_plot, df_model_stats, canvas_frame, toolbar_frame, selected_models=None):
    # ... (implementation from previous version, ensure FIG_MIN_HEIGHT and FIG_BASE_WIDTH are used correctly)
    if df_runs.empty: messagebox.showerror("Data Error", "Benchmark runs data is empty."); return
    if 'category' not in df_runs.columns or 'model_id' not in df_runs.columns or 'correct' not in df_runs.columns:
        messagebox.showerror("Data Error", "Required columns missing for category plot.")
        return

    df_runs['correct'] = pd.to_numeric(df_runs['correct'], errors='coerce').fillna(0)
    category = categories_to_plot[0] 

    df_category_data = df_runs[df_runs['category'] == category].copy()
    
    plot_title_suffix = ""
    if selected_models:
        df_category_data = df_category_data[df_category_data['model_id'].isin(selected_models)]
        plot_title_suffix = f" (Filtered: {len(df_category_data['model_id'].unique())} models)"
    else:
        plot_title_suffix = f" (Top {TOP_N_MODELS_PER_CATEGORY} Models)"


    if df_category_data.empty:
        messagebox.showinfo("No Data", f"No data for category: {category} (after model filter if any).")
        return

    model_agg = df_category_data.groupby('model_id').agg(
        correct_answers=('correct', 'sum'), total_questions=('correct', 'count')
    ).reset_index()
    
    model_agg = model_agg[model_agg['total_questions'] >= MIN_QUESTIONS_FOR_CATEGORY_PLOT]
    if model_agg.empty:
        messagebox.showinfo("No Data", f"Not enough data (after MIN_Q) for category '{category}'.")
        return

    model_agg['percentage_correct'] = (model_agg['correct_answers'] / model_agg['total_questions']) * 100
    
    df_sorted = model_agg.sort_values('percentage_correct', ascending=False)
    if not selected_models: 
        df_sorted = df_sorted.head(TOP_N_MODELS_PER_CATEGORY)
    
    if df_sorted.empty:
        messagebox.showinfo("No Data", f"Not enough model data for category '{category}' after sorting/filtering.")
        return

    num_models_plot = len(df_sorted)
    fig_height = max(FIG_MIN_HEIGHT, num_models_plot * FIG_HEIGHT_PER_ITEM_BAR)
    fig_width = FIG_BASE_WIDTH

    fig, ax = plt.subplots(figsize=(fig_width, fig_height))
    bars = sns.barplot(x='percentage_correct', y='model_id', data=df_sorted, ax=ax, 
                       palette="mako", hue='model_id', dodge=False, legend=False,
                       order=df_sorted['model_id'].tolist())
                       
    ax.set_xlabel("Correctness (%)", fontsize=plt.rcParams['axes.labelsize'])
    ax.set_ylabel("Model ID", fontsize=plt.rcParams['axes.labelsize'])
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

    filename_cat = "".join(c if c.isalnum() or c in (' ', '_', '-') else '_' for c in category).replace(' ', '_')
    title = f"Models for Category: {category}{plot_title_suffix}"
    save_or_show_plot(fig, canvas_frame, toolbar_frame, f"category_{filename_cat}_models_bar", title, show_inline=True)

# --- Application Class ---
class BenchmarkApp:
    def __init__(self, root):
        self.root = root
        self.root.title("Benchmark Graph Generator")
        self.root.geometry("1200x800") # Initial size

        # Use a PanedWindow for resizable sidebar
        self.paned_window = ttk.PanedWindow(root, orient=tk.HORIZONTAL)
        self.paned_window.pack(fill=tk.BOTH, expand=True)

        # --- Controls Frame (Sidebar) ---
        # This frame will be added to the paned window
        controls_outer_frame = ttk.Frame(self.paned_window, width=350) # Initial width for sidebar
        self.paned_window.add(controls_outer_frame, weight=1) # weight makes it resizable relative to other panes
        # controls_outer_frame.pack_propagate(False) # Not needed with PanedWindow's sash


        load_frame = ttk.LabelFrame(controls_outer_frame, text="Load Data", padding="10")
        load_frame.pack(padx=5, pady=5, fill="x", side=tk.TOP)
        self.db_button = ttk.Button(load_frame, text="Select benchmark.db", command=self.load_db)
        self.db_button.pack(fill="x", pady=2)
        self.db_status_label = ttk.Label(load_frame, text="No DB loaded.", wraplength=300)
        self.db_status_label.pack(fill="x", pady=2)
        self.clear_dir_button = ttk.Button(load_frame, text="Clear Output Dir", command=lambda: clear_output_directory(OUTPUT_DIR))
        self.clear_dir_button.pack(fill="x", pady=2)

        plot_controls_frame = ttk.LabelFrame(controls_outer_frame, text="Plot Controls", padding="10")
        plot_controls_frame.pack(padx=5, pady=5, fill="both", expand=True, side=tk.TOP)

        overall_plots_frame = ttk.LabelFrame(plot_controls_frame, text="Overall Model Stats", padding="5")
        overall_plots_frame.pack(fill="x", pady=3, anchor="n")
        ttk.Button(overall_plots_frame, text="Correctness Bar", command=self.plot_overall_correctness_gui).pack(fill="x", pady=2)
        ttk.Button(overall_plots_frame, text="Speeds Scatter", command=self.plot_speeds_scatter_gui).pack(fill="x", pady=2)
        ttk.Button(overall_plots_frame, text="Prompt Speed vs Quality", command=self.plot_prompt_speed_quality_gui).pack(fill="x", pady=2)
        ttk.Button(overall_plots_frame, text="Gen Speed vs Quality", command=self.plot_gen_speed_quality_gui).pack(fill="x", pady=2)
        
        category_plots_frame = ttk.LabelFrame(plot_controls_frame, text="Category Stats", padding="5")
        category_plots_frame.pack(fill="x", pady=3, anchor="n")
        ttk.Button(category_plots_frame, text="Category Heatmap", command=self.plot_category_heatmap_gui).pack(fill="x", pady=2)
        
        ttk.Label(category_plots_frame, text="Category for Bar Chart:").pack(fill="x", pady=(5,0))
        self.category_var = tk.StringVar()
        self.category_dropdown = ttk.Combobox(category_plots_frame, textvariable=self.category_var, state="readonly", width=28)
        self.category_dropdown.pack(fill="x", pady=2)
        ttk.Button(category_plots_frame, text="Top Models in Category (Bar)", command=self.plot_category_bar_gui).pack(fill="x", pady=2)

        model_select_frame = ttk.LabelFrame(plot_controls_frame, text="Filter Models (for All Plots)", padding="5")
        model_select_frame.pack(fill="both", expand=True, pady=3, anchor="n")
        
        # Frame for listbox and scrollbar to ensure button is below
        listbox_container = ttk.Frame(model_select_frame)
        listbox_container.pack(fill=tk.BOTH, expand=True)

        self.model_listbox = tk.Listbox(listbox_container, selectmode=tk.MULTIPLE, height=8, exportselection=False) # Increased height
        model_scrollbar = ttk.Scrollbar(listbox_container, orient="vertical", command=self.model_listbox.yview)
        self.model_listbox.configure(yscrollcommand=model_scrollbar.set)
        
        model_scrollbar.pack(side=tk.RIGHT, fill=tk.Y)
        self.model_listbox.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        
        ttk.Button(model_select_frame, text="Clear Selection", command=lambda: self.model_listbox.selection_clear(0, tk.END)).pack(fill=tk.X, pady=(5,0), side=tk.BOTTOM)


        # --- Plotting Area (Main Area on the right) ---
        # This frame will also be added to the paned window
        plot_main_frame = ttk.Frame(self.paned_window) 
        self.paned_window.add(plot_main_frame, weight=3) # Give plot area more weight

        self.toolbar_frame = ttk.Frame(plot_main_frame)
        self.toolbar_frame.pack(fill="x", side=tk.BOTTOM) 
        
        self.canvas_frame = ttk.Frame(plot_main_frame)
        self.canvas_frame.pack(fill="both", expand=True, side=tk.TOP)
        self.initial_message_label = ttk.Label(self.canvas_frame, text="Select a DB and a plot type.", font=("Arial", 14))
        self.initial_message_label.pack(padx=20, pady=20, expand=True)

    def load_db(self):
        # ... (same as before)
        global db_path_global, df_model_stats_global, df_benchmark_runs_global, all_model_names_global, all_categories_global
        
        filepath = filedialog.askopenfilename(
            title="Select benchmark.db",
            filetypes=(("Database files", "*.db"), ("All files", "*.*"))
        )
        if not filepath: return

        db_path_global = filepath
        try:
            conn = sqlite3.connect(db_path_global)
            df_model_stats_global = pd.read_sql_query("SELECT * FROM model_stats", conn)
            df_benchmark_runs_global = pd.read_sql_query("SELECT * FROM benchmark_runs", conn)
            conn.close()

            if 'percentage_correct' not in df_model_stats_global.columns and \
               'correct_answers' in df_model_stats_global.columns and \
               'total_questions' in df_model_stats_global.columns:
                df_model_stats_global['correct_answers'] = pd.to_numeric(df_model_stats_global['correct_answers'], errors='coerce').fillna(0)
                df_model_stats_global['total_questions'] = pd.to_numeric(df_model_stats_global['total_questions'], errors='coerce').fillna(0)
                df_model_stats_global['percentage_correct'] = np.where(
                    df_model_stats_global['total_questions'] > 0,
                    (df_model_stats_global['correct_answers'] / df_model_stats_global['total_questions']) * 100,
                    0).round(1)
            elif 'percentage_correct' in df_model_stats_global.columns:
                 df_model_stats_global['percentage_correct'] = pd.to_numeric(df_model_stats_global['percentage_correct'], errors='coerce').fillna(0).round(1)

            self.db_status_label.config(text=f"Loaded: {os.path.basename(db_path_global)}")
            messagebox.showinfo("Success", "Database loaded successfully!")

            all_model_names_global = sorted(df_model_stats_global['model_id'].unique().tolist())
            self.model_listbox.delete(0, tk.END)
            for model_name in all_model_names_global: self.model_listbox.insert(tk.END, model_name)

            if not df_benchmark_runs_global.empty and 'category' in df_benchmark_runs_global.columns:
                df_benchmark_runs_global['category'] = df_benchmark_runs_global['category'].fillna('Uncategorized').astype(str)
                all_categories_global = sorted(df_benchmark_runs_global['category'].unique().tolist())
                self.category_dropdown['values'] = all_categories_global
                if all_categories_global: self.category_var.set(all_categories_global[0])
            else: self.category_dropdown['values'] = []
            
            if self.initial_message_label:
                self.initial_message_label.pack_forget() 
                self.initial_message_label = None
        except Exception as e:
            self.db_status_label.config(text="Error loading DB.")
            messagebox.showerror("Error", f"Failed to load database: {e}")
            df_model_stats_global = pd.DataFrame(); df_benchmark_runs_global = pd.DataFrame()


    def get_selected_models(self):
        selected_indices = self.model_listbox.curselection()
        return [self.model_listbox.get(i) for i in selected_indices] if selected_indices else None

    # --- GUI Callbacks (now passing selected_models to all relevant plots) ---
    def plot_overall_correctness_gui(self):
        if df_model_stats_global.empty: messagebox.showwarning("No Data", "Please load a database first."); return
        selected_models = self.get_selected_models()
        plot_overall_model_correctness_bar(df_model_stats_global.copy(), self.canvas_frame, self.toolbar_frame, 
                                           selected_models=selected_models)

    def plot_speeds_scatter_gui(self):
        if df_model_stats_global.empty: messagebox.showwarning("No Data", "Please load a database first."); return
        selected_models = self.get_selected_models()
        plot_overall_model_speeds_scatter(df_model_stats_global.copy(), self.canvas_frame, self.toolbar_frame, 
                                          label_points=True, selected_models=selected_models)

    def plot_prompt_speed_quality_gui(self):
        if df_model_stats_global.empty: messagebox.showwarning("No Data", "Please load a database first."); return
        selected_models = self.get_selected_models()
        plot_speed_vs_quality(df_model_stats_global.copy(), 'avg_prompt_eval_per_second', 'Prompt Eval Speed', 
                              self.canvas_frame, self.toolbar_frame, selected_models=selected_models)

    def plot_gen_speed_quality_gui(self):
        if df_model_stats_global.empty: messagebox.showwarning("No Data", "Please load a database first."); return
        selected_models = self.get_selected_models()
        plot_speed_vs_quality(df_model_stats_global.copy(), 'avg_tokens_per_second', 'Token Generation Speed', 
                              self.canvas_frame, self.toolbar_frame, selected_models=selected_models)
    
    def plot_category_heatmap_gui(self):
        if df_benchmark_runs_global.empty or df_model_stats_global.empty: messagebox.showwarning("No Data", "Please load a database first."); return
        selected_models = self.get_selected_models()
        plot_category_correctness_heatmap(df_benchmark_runs_global.copy(), df_model_stats_global.copy(), 
                                          self.canvas_frame, self.toolbar_frame, 
                                          top_m_models=TOP_N_MODELS_OVERALL, selected_models=selected_models)
                                          
    def plot_category_bar_gui(self):
        if df_benchmark_runs_global.empty: messagebox.showwarning("No Data", "Please load a database first."); return
        selected_category = self.category_var.get()
        if not selected_category: messagebox.showwarning("No Category", "Please select a category."); return
        selected_models = self.get_selected_models()
        plot_top_models_for_specific_categories(df_benchmark_runs_global.copy(), [selected_category], 
                                                df_model_stats_global.copy(),
                                                self.canvas_frame, self.toolbar_frame, selected_models=selected_models)


if __name__ == "__main__":
    clear_output_directory(OUTPUT_DIR) 
    root = tk.Tk()
    app = BenchmarkApp(root)
    root.mainloop()