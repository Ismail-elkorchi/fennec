#!/usr/bin/env bash
set -euo pipefail

today=$(date +%Y-%m-%d)
fail=0

check_file() {
  local file="$1"

  if [ ! -f "$file" ]; then
    echo "Missing decisions file: $file"
    return 1
  fi

  awk -v today="$today" -v file="$file" '
    function reset(){
      delete seen
      review_date=""
      in_review=0
    }
    function check_block(){
      if(adr==""){return}
      for(i=1;i<=req_count;i++){
        h=req[i]
        if(!(h in seen)){
          printf("%s: %s missing heading: %s\n", file, adr, h)
          fail=1
        }
      }
      if(review_date==""){
        printf("%s: %s missing Review-By date\n", file, adr)
        fail=1
      } else if(review_date !~ /^[0-9]{4}-[0-9]{2}-[0-9]{2}$/){
        printf("%s: %s Review-By date invalid: %s\n", file, adr, review_date)
        fail=1
      } else if(review_date < today){
        printf("%s: %s Review-By in past: %s (today %s)\n", file, adr, review_date, today)
        fail=1
      }
    }
    BEGIN{
      req_count=split("Context|Decision|Alternatives Considered|Evidence|Falsifiers|Unknowns|Review-By", req, "|")
      adr=""
      fail=0
      reset()
    }
    {
      line=$0
      if(line ~ /^## ADR-/){
        check_block()
        adr=line
        reset()
        next
      }
      if(line ~ /^### /){
        heading=substr(line,5)
        seen[heading]=1
        if(heading=="Review-By"){in_review=1} else {in_review=0}
        next
      }
      if(in_review){
        trimmed=line
        gsub(/^[[:space:]]+|[[:space:]]+$/, "", trimmed)
        if(trimmed!=""){
          review_date=trimmed
          in_review=0
        }
      }
    }
    END{
      check_block()
      if(fail==1){exit 1}
    }
  ' "$file"
}

if ! check_file "docs/decisions.md"; then
  fail=1
fi

if [ "$fail" -ne 0 ]; then
  echo "Decision checks failed."
  exit 1
fi

echo "Decision checks passed."
