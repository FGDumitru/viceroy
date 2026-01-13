#!/bin/bash
# Start Chrome with remote debugging for Playwright CDP connection
# This creates a separate profile so your main Chrome can stay open
# Supports macOS and Linux

DEBUG_PORT=9222

# Detect OS
OS_TYPE="$(uname -s)"

case "$OS_TYPE" in
    Darwin*)
        # macOS
        CHROME_BIN="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
        DEBUG_DIR="$HOME/Library/Application Support/Google Chrome Debug"
        OS_NAME="macOS"
        ;;

    Linux*)
        # Linux - try multiple Chrome/Chromium locations
        if [ -x "$(command -v google-chrome)" ]; then
            CHROME_BIN="google-chrome"
            DEBUG_DIR="$HOME/.config/google-chrome-debug"
        elif [ -x "$(command -v google-chrome-stable)" ]; then
            CHROME_BIN="google-chrome-stable"
            DEBUG_DIR="$HOME/.config/google-chrome-debug"
        elif [ -x "$(command -v chromium)" ]; then
            CHROME_BIN="chromium"
            DEBUG_DIR="$HOME/.config/chromium-debug"
        elif [ -x "$(command -v chromium-browser)" ]; then
            CHROME_BIN="chromium-browser"
            DEBUG_DIR="$HOME/.config/chromium-debug"
        elif [ -f "/opt/google/chrome/google-chrome" ]; then
            CHROME_BIN="/opt/google/chrome/google-chrome"
            DEBUG_DIR="$HOME/.config/google-chrome-debug"
        elif [ -f "/usr/bin/google-chrome-stable" ]; then
            CHROME_BIN="/usr/bin/google-chrome-stable"
            DEBUG_DIR="$HOME/.config/google-chrome-debug"
        else
            echo "ERROR: Could not find Chrome or Chromium on your system."
            echo ""
            echo "Please install one of the following:"
            echo "  - Google Chrome: https://www.google.com/chrome/"
            echo "  - Chromium: https://www.chromium.org/"
            echo ""
            echo "Or set the Chrome binary path manually in this script."
            exit 1
        fi
        OS_NAME="Linux"
        ;;

    *)
        echo "ERROR: Unsupported operating system: $OS_TYPE"
        echo "This script supports macOS and Linux only."
        exit 1
        ;;
esac

# Create debug profile directory if it doesn't exist
mkdir -p "$DEBUG_DIR"

echo "=== Starting Chrome with Remote Debugging ==="
echo ""
echo "OS: $OS_NAME"
echo "Chrome: $CHROME_BIN"
echo "Debug Profile: $DEBUG_DIR"
echo "Port: $DEBUG_PORT"
echo ""
echo "Connect with: 'connect_cdp' => 'http://localhost:$DEBUG_PORT'"
echo ""

# Start Chrome with the debug profile
"$CHROME_BIN" \
  --remote-debugging-port=$DEBUG_PORT \
  --user-data-dir="$DEBUG_DIR" \
  > /dev/null 2>&1 &

CHROME_PID=$!

echo "Chrome is starting (PID: $CHROME_PID)..."
echo ""
echo "To verify it's working:"
echo "  curl http://localhost:$DEBUG_PORT/json"
echo ""
echo "To stop Chrome later:"
echo "  kill $CHROME_PID"
echo ""
echo "Or run: php examples/playwright_cdp.php"
echo ""
