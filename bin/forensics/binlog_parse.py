#!/usr/bin/env python3
"""Parse `mysqlbinlog --base64-output=DECODE-ROWS -v` output on stdin into a
compact JSON timeline of notable/destructive events. Read-only, idempotent:
same binlogs -> same output, no side effects. Runs inside the forensics toolbox
(mounted at /scripts) where the version-matched mysqlbinlog + RO prod binlogs live.
"""
import sys
import re
import json

hdr = re.compile(r'^#(\d{6})\s+(\d{1,2}:\d{2}:\d{2})\s+server id')
rowop = re.compile(r'^### (DELETE FROM|UPDATE|INSERT INTO) `([^`]+)`\.`([^`]+)`')
ddl = re.compile(r'\b(DROP\s+DATABASE|DROP\s+SCHEMA|DROP\s+TABLE|TRUNCATE(?:\s+TABLE)?|ALTER\s+TABLE)\b', re.I)
delstmt = re.compile(r'^\s*DELETE\s+FROM\s+`?(\w+)`?', re.I)
updstmt = re.compile(r'^\s*UPDATE\s+`?(\w+)`?', re.I)

SEV = {"DROP": "critical", "TRUNCATE": "critical", "DELETE": "high",
       "UPDATE": "medium", "ALTER": "medium", "INSERT": "low"}

ts = None
agg = {}        # (ts, type, table) -> row count   (row-format events)
events = []     # discrete statement events (DDL, stmt DML)


def add(ts, typ, table, rows=None, sql=None):
    events.append({"ts": ts, "type": typ, "table": table, "rows": rows,
                   "severity": SEV.get(typ, "info"), "sql": sql})


for line in sys.stdin:
    m = hdr.match(line)
    if m:
        g1 = m.group(1)
        hh = m.group(2)
        if len(hh) == 7:
            hh = "0" + hh
        ts = "20%s-%s-%s %s" % (g1[0:2], g1[2:4], g1[4:6], hh)
        continue
    m = rowop.match(line)
    if m:
        typ = {"DELETE FROM": "DELETE", "UPDATE": "UPDATE", "INSERT INTO": "INSERT"}[m.group(1)]
        key = (ts, typ, m.group(3))
        agg[key] = agg.get(key, 0) + 1
        continue
    if ddl.search(line):
        kw = ddl.search(line).group(1).upper().split()[0]
        typ = "DROP" if kw == "DROP" else ("TRUNCATE" if kw.startswith("TRUNC") else "ALTER")
        add(ts, typ, None, None, line.strip()[:140])
        continue
    m = delstmt.match(line)
    if m:
        add(ts, "DELETE", m.group(1), None, line.strip()[:140])
        continue
    m = updstmt.match(line)
    if m:
        add(ts, "UPDATE", m.group(1), None, line.strip()[:140])
        continue

for (t, typ, tbl), n in agg.items():
    add(t, typ, tbl, n, None)

# Summary over everything (so the routine churn is still accounted for).
by_severity = {}
by_table = {}
for e in events:
    by_severity[e["severity"]] = by_severity.get(e["severity"], 0) + 1
    if e["table"]:
        bt = by_table.setdefault(e["table"], {"events": 0, "rows": 0})
        bt["events"] += 1
        bt["rows"] += e["rows"] or 0

# Keep ALL destructive/notable events (critical + high) — a DROP must never be
# truncated away. Cap the routine medium/low churn, keeping the biggest by rows.
LOUD = {"critical", "high"}
loud = [e for e in events if e["severity"] in LOUD]
quiet = [e for e in events if e["severity"] not in LOUD]
quiet.sort(key=lambda e: (e["rows"] or 0), reverse=True)
quiet = quiet[:300]
kept = loud + quiet
kept.sort(key=lambda e: (e["ts"] or ""))

# Top tables by total rows touched in the window (the "what moved most" overview).
top_tables = sorted(
    ({"table": k, "events": v["events"], "rows": v["rows"]} for k, v in by_table.items()),
    key=lambda x: x["rows"], reverse=True)[:10]

print(json.dumps({
    "events": kept,
    "count": len(events),
    "kept": len(kept),
    "truncated": len(events) > len(kept),
    "by_severity": by_severity,
    "top_tables": top_tables,
}))
