#!/usr/bin/env bash
# Publiceert de statische Nuxt-build in de public-map van Laravel.
#
# Die map is gedeeld: Laravel levert er zijn front controller en .htaccess,
# de deploy zet er de gegenereerde site naast. Alles wat in git staat blijft
# dus met rust; de rest wordt vervangen door de nieuwe build.
#
# Sourcen voor de helpers, of uitvoeren met:
#   PUBLISH_OUTPUT=… PUBLISH_PUBLIC=… [PUBLISH_UI_PREV=…] bash publish-nuxt-public.sh
set -euo pipefail

# Mappen die Laravel zelf beheert en die de publicatie nooit mag weggooien.
# `storage` is de symlink van `artisan storage:link`, `.well-known` gebruikt
# DirectAdmin voor certificaatvernieuwing.
KLIMAATX_PUBLISH_KEEP=(storage .well-known .deploy hot build)

# De bestanden die Laravel in git heeft staan (index.php, .htaccess, favicon).
# Uit git afgeleid in plaats van hier opgesomd: zet iemand er later een bestand
# bij, dan blijft dat vanzelf gespaard.
klimaatx_tracked_public_entries() {
  local public_dir="$1"
  local repo_root entry
  repo_root="$(git -C "$public_dir" rev-parse --show-toplevel 2>/dev/null || true)"

  if [[ -z "$repo_root" ]]; then
    # Geen git-checkout (bijvoorbeeld een losse kopie): val terug op de
    # bestanden die er hoe dan ook horen te staan.
    printf '%s\n' index.php .htaccess favicon.ico
    return 0
  fi

  git -C "$public_dir" ls-files . | while IFS= read -r entry; do
    # Alleen het eerste pad-element: een map telt als geheel mee.
    printf '%s\n' "${entry%%/*}"
  done | sort -u
}

klimaatx_rsync_excludes() {
  local public_dir="$1"
  local entry
  for entry in "${KLIMAATX_PUBLISH_KEEP[@]}"; do
    printf -- '--exclude=/%s\n' "$entry"
  done
  while IFS= read -r entry; do
    [[ -z "$entry" ]] && continue
    printf -- '--exclude=/%s\n' "$entry"
  done < <(klimaatx_tracked_public_entries "$public_dir")
}

_klimaatx_copy_tree() {
  local src="$1" dest="$2"
  rm -rf "$dest"
  mkdir -p "$dest"
  cp -a "$src"/. "$dest"/
}

# Momentopname van de onderdelen die bij een mislukte deploy een halve site
# opleveren: de hashed assets en de HTML die ernaar verwijst. media/ en v2/
# blijven buiten de snapshot — die zijn groot en veranderen zelden, en komen
# terug door de deploy opnieuw te draaien.
klimaatx_snapshot_ui() {
  local public_dir="$1" prev_dir="$2" f
  rm -rf "$prev_dir"
  mkdir -p "$prev_dir"
  if [[ -d "$public_dir/_nuxt" ]]; then
    _klimaatx_copy_tree "$public_dir/_nuxt" "$prev_dir/_nuxt"
  fi
  for f in index.html 200.html 404.html; do
    [[ -f "$public_dir/$f" ]] && cp "$public_dir/$f" "$prev_dir/$f"
  done
  return 0
}

klimaatx_restore_ui() {
  local public_dir="$1" prev_dir="$2" f
  if [[ ! -d "$prev_dir/_nuxt" ]] || [[ -z "$(ls -A "$prev_dir/_nuxt" 2>/dev/null || true)" ]]; then
    echo "publish: kan niet terugzetten — geen momentopname in $prev_dir/_nuxt" >&2
    return 1
  fi
  echo "publish: UI terugzetten uit $prev_dir" >&2
  rm -rf "$public_dir/_nuxt"
  _klimaatx_copy_tree "$prev_dir/_nuxt" "$public_dir/_nuxt"
  for f in index.html 200.html 404.html; do
    if [[ -f "$prev_dir/$f" ]]; then
      cp "$prev_dir/$f" "$public_dir/$f"
    fi
  done
  return 0
}

klimaatx_verify_publish() {
  local public_dir="$1" f
  if [[ ! -f "$public_dir/index.html" ]]; then
    echo "publish: $public_dir/index.html ontbreekt na publicatie." >&2
    return 1
  fi
  if [[ ! -d "$public_dir/_nuxt" ]] || [[ -z "$(ls -A "$public_dir/_nuxt" 2>/dev/null || true)" ]]; then
    echo "publish: $public_dir/_nuxt ontbreekt of is leeg na publicatie." >&2
    return 1
  fi
  # Zonder deze twee is de API weg terwijl de site er nog staat: de
  # formulieren geven dan een fout die niemand aan de deploy koppelt.
  for f in index.php .htaccess; do
    if [[ ! -f "$public_dir/$f" ]]; then
      echo "publish: Laravel-bestand weg na publicatie: $public_dir/$f" >&2
      return 1
    fi
  done
  if [[ ! -f "$public_dir/v2/index.html" ]]; then
    echo "publish: de tweede landingspagina ($public_dir/v2/index.html) ontbreekt." >&2
    return 1
  fi
  if [[ ! -e "$public_dir/storage" ]]; then
    echo "publish: let op — $public_dir/storage ontbreekt (draai: php artisan storage:link)" >&2
  fi
  return 0
}

klimaatx_publish_ui() {
  local output_dir="$1" public_dir="$2" prev_dir="$3"
  local excludes=()

  if [[ ! -f "$output_dir/index.html" ]]; then
    echo "publish: geen index.html in $output_dir" >&2
    return 1
  fi
  if [[ ! -d "$output_dir/_nuxt" ]] || [[ -z "$(ls -A "$output_dir/_nuxt" 2>/dev/null || true)" ]]; then
    echo "publish: geen _nuxt/ in $output_dir" >&2
    return 1
  fi

  echo "publish: momentopname van de live UI → $prev_dir" >&2
  klimaatx_snapshot_ui "$public_dir" "$prev_dir"

  # Geen mapfile: die kent bash 3.2 (macOS) niet, en dit script draait ook
  # lokaal om de publicatie te controleren.
  local line
  while IFS= read -r line; do
    [[ -n "$line" ]] && excludes+=("$line")
  done < <(klimaatx_rsync_excludes "$public_dir")

  echo "publish: rsync → $public_dir/ (blijft staan: ${excludes[*]//--exclude=\//})" >&2

  # --delay-updates zet de nieuwe bestanden pas aan het eind op hun plek, zodat
  # het venster waarin de HTML naar nog-niet-geschreven assets wijst zo kort
  # mogelijk is. Volledig atomisch is het niet; dat zou een tweede kopie van
  # ~70 MB media kosten voor een site met dit bezoek.
  if ! rsync -a --delete --delay-updates "${excludes[@]}" "$output_dir/" "$public_dir/"; then
    echo "publish: rsync mislukt — UI terugzetten" >&2
    klimaatx_restore_ui "$public_dir" "$prev_dir" || true
    return 1
  fi

  if ! klimaatx_verify_publish "$public_dir"; then
    klimaatx_restore_ui "$public_dir" "$prev_dir" || true
    return 1
  fi

  echo "publish: klaar (Laravel-bestanden en storage ongemoeid)"
  return 0
}

# Uitgevoerd in plaats van gesourcet: publiceren met paden uit de omgeving.
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
  PUBLISH_OUTPUT="${PUBLISH_OUTPUT:?PUBLISH_OUTPUT is verplicht}"
  PUBLISH_PUBLIC="${PUBLISH_PUBLIC:?PUBLISH_PUBLIC is verplicht}"
  PUBLISH_UI_PREV="${PUBLISH_UI_PREV:-$(cd "$PUBLISH_PUBLIC/.." && pwd)/.deploy/ui.prev}"
  klimaatx_publish_ui "$PUBLISH_OUTPUT" "$PUBLISH_PUBLIC" "$PUBLISH_UI_PREV"
fi
