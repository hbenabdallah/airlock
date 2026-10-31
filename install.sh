#!/bin/sh
# Installs the Airlock binary for this machine from the project's GitHub releases, after checking it against the
# release's SHA256SUMS.
#
#   curl -fsSL https://raw.githubusercontent.com/hbenabdallah/airlock/main/install.sh | sh
#   curl -fsSL https://raw.githubusercontent.com/hbenabdallah/airlock/main/install.sh | sh -s -- 1.0.0
#
# AIRLOCK_INSTALL_DIR picks where it goes (default: ~/.local/bin).
set -eu

releases="${AIRLOCK_RELEASES_URL:-https://github.com/hbenabdallah/airlock/releases}"
install_dir="${AIRLOCK_INSTALL_DIR:-$HOME/.local/bin}"

fail() {
    printf 'airlock: %s\n' "$1" >&2
    exit 1
}

usage() {
    printf 'Usage: install.sh [VERSION]\n\nInstalls Airlock VERSION (1.0.0 or v1.0.0), or the latest release, into %s.\n' "$install_dir"
}

target() {
    case "$(uname -s)" in
        Darwin) system=macos ;;
        Linux) system=linux ;;
        *) fail "no binary for $(uname -s): Airlock runs on macOS and Linux" ;;
    esac
    case "$(uname -m)" in
        x86_64 | amd64) architecture=x86_64 ;;
        arm64 | aarch64) architecture=aarch64 ;;
        *) fail "no binary for the $(uname -m) architecture" ;;
    esac
    if [ "$system" = macos ] && [ "$architecture" = x86_64 ]; then
        fail "no binary for Intel Macs: install Airlock with Docker or PHP 8.5, see the README"
    fi
    printf '%s-%s' "$system" "$architecture"
}

download() {
    if command -v curl > /dev/null 2>&1; then
        curl -fsSL -o "$2" "$1"
    elif command -v wget > /dev/null 2>&1; then
        wget -q -O "$2" "$1"
    else
        fail "curl or wget is needed to download Airlock"
    fi
}

sha256() {
    if command -v sha256sum > /dev/null 2>&1; then
        sha256sum "$1" | cut -d ' ' -f 1
    elif command -v shasum > /dev/null 2>&1; then
        shasum -a 256 "$1" | cut -d ' ' -f 1
    else
        fail "sha256sum or shasum is needed to check the download"
    fi
}

case "${1:-}" in
    -h | --help)
        usage
        exit 0
        ;;
    '') base="$releases/latest/download" ;;
    *) base="$releases/download/v${1#v}" ;;
esac

binary="airlock-$(target)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

printf 'Downloading %s from %s\n' "$binary" "$base"
download "$base/$binary" "$work/$binary" || fail "could not download $base/$binary: is there such a release?"
download "$base/SHA256SUMS" "$work/SHA256SUMS" || fail "could not download $base/SHA256SUMS"

expected="$(awk -v name="$binary" '$2 == name || $2 == "*" name { print $1 }' "$work/SHA256SUMS")"
[ -n "$expected" ] || fail "SHA256SUMS lists no $binary"
[ "$(sha256 "$work/$binary")" = "$expected" ] || fail "$binary does not match its checksum: not installed"

mkdir -p "$install_dir"
chmod +x "$work/$binary"
mv "$work/$binary" "$install_dir/airlock"
printf 'Installed %s\n' "$install_dir/airlock"

case ":$PATH:" in
    *":$install_dir:"*) ;;
    *) printf 'Add %s to your PATH to run airlock by name.\n' "$install_dir" ;;
esac
if [ "$(uname -s)" = Linux ] && ! command -v bwrap > /dev/null 2>&1; then
    printf 'Install bubblewrap too, or Airlock runs commands without a sandbox and asks about each one.\n'
fi
printf 'Then, from the project Airlock is to guard: claude mcp add airlock -- %s mcp:server\n' "$install_dir/airlock"
