#!/usr/bin/env bash
# Runs SigNoz's own server-side validation (PostableDashboardV2.UnmarshalJSON, the code behind
# "Import JSON") on every *.json in a folder. Needs docker and network on the first run
# (sparse clone of SigNoz + Go modules, about 1 GB under ~/.cache/rcp-signoz-validate).
#
# Usage: validate.sh <folder-with-dashboards> [signoz-tag, default v0.142.1]
set -euo pipefail

DASHBOARDS="$(realpath "$1")"
TAG="${2:-v0.142.1}"
CACHE="$HOME/.cache/rcp-signoz-validate"
SRC="$CACHE/signoz-$TAG"
mkdir -p "$CACHE/gocache" "$CACHE/gomod"

if [ ! -d "$SRC" ]; then
  git clone --quiet --depth 1 --branch "$TAG" --filter=blob:none --sparse https://github.com/SigNoz/signoz.git "$SRC"
  git -C "$SRC" sparse-checkout set pkg ee cmd
fi

cat > "$SRC/pkg/types/dashboardtypes/zz_rcp_test.go" <<'EOF'
package dashboardtypes

import (
	"encoding/json"
	"os"
	"path/filepath"
	"testing"
)

func TestRCPDashboards(t *testing.T) {
	files, _ := filepath.Glob("/rcp/*.json")
	if len(files) == 0 {
		t.Fatal("no *.json files found")
	}
	for _, f := range files {
		body, err := os.ReadFile(f)
		if err != nil {
			t.Fatal(err)
		}
		var p PostableDashboardV2
		if err := json.Unmarshal(body, &p); err != nil {
			t.Errorf("%s: %v", filepath.Base(f), err)
			continue
		}
		t.Logf("%s: OK (%d panels)", filepath.Base(f), len(p.Spec.Panels))
	}
}
EOF

docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -e GOCACHE=/gocache -e GOMODCACHE=/gomod \
  -e GOFLAGS=-buildvcs=false -v "$SRC:/src" -v "$DASHBOARDS:/rcp:ro" \
  -v "$CACHE/gocache:/gocache" -v "$CACHE/gomod:/gomod" -w /src golang:1.25 \
  go test ./pkg/types/dashboardtypes/ -run TestRCPDashboards -v -count=1 2>&1 | grep -v '^go: downloading'
