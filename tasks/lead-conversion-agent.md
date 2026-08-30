---
id: lead-conversion-agent
title: "Autonome lead-to-appointment agent workflow met CRM dashboard"
owner: klimaatx
status: in_progress
branch: claude/lead-conversion-agent-2cxnrz
scope_in:
  - "apps/api: Laravel 12 CRM + orchestratie-engine (leads, quotes, calls, appointments, events, catalogus, sequences, settings)"
  - "Mailbox intake via IMAP + parsers voor eigen formulier en leadportalen"
  - "Verrijking: capaciteitsberekening, systeemadvies, prijs- en montagetijdraming"
  - "ElevenLabs Conversational AI uitbelintegratie (kwalificatie, conversie, afsluiting) incl. post-call webhook"
  - "Prijsindicatie genereren en mailen direct na het kwalificatiegesprek; bindende offerte pas na de opname ter plaatse"
  - "Opname ter plaatse inplannen vanuit het conversiegesprek"
  - "Opvolgbelletje op T+1u en chase-cadans (bel + mail) bij geen gehoor"
  - "Afspraak inplannen via Google Calendar of Apple Calendar (CalDAV), met ICS-fallback"
  - "Notificatiemails naar de ondernemer bij elke mijlpaal"
  - "apps/web: dashboard/CRM (funnel-analytics, leadoverzicht, detail-timeline, herstart van stappen, data bewerken, catalogusbeheer, instellingen)"
  - "Voorlopige prijs- en montagetijdcatalogus op basis van groothandel-/marktonderzoek"
  - "Tests, quality gates, runbooks, artifacts"
scope_out:
  - "Betaalintegratie / facturatie"
  - "WhatsApp- en SMS-kanaal (alleen voorbereid via channel-enum, niet geimplementeerd)"
  - "Productie-deploy van de API op de VPS (runbook wel bijgewerkt)"
  - "Wijzigingen aan de bestaande landingspagina-secties"
acceptance:
  - "Een lead-e-mail in de mailbox leidt automatisch tot een lead met status enriched en een conceptofferte-berekening"
  - "Kwalificatiegesprek wordt gestart binnen de belvenster-instellingen; buiten het venster wordt uitgesteld"
  - "Post-call webhook is HMAC-geverifieerd en werkt lead, transcript en verzamelde velden bij"
  - "Na een beantwoord kwalificatiegesprek wordt binnen dezelfde flow een vrijblijvende prijsindicatie gemaild"
  - "60 minuten na de prijsindicatie wordt automatisch een conversiegesprek ingepland dat een opname ter plaatse afspreekt"
  - "Een bindende offerte kan niet verstuurd worden voordat de opname is afgerond"
  - "Bij akkoord op de offerte wordt een installatieafspraak aangemaakt bij de geconfigureerde provider"
  - "Bij geen gehoor loopt een chase-cadans met bel- en mailstappen tot een configureerbaar maximum"
  - "De ondernemer ontvangt bij elke mijlpaal een notificatiemail"
  - "Dashboard toont funnel, leads, timeline; stappen kunnen opnieuw worden afgetrapt en gegevens aangepast"
  - "Catalogusprijzen en montagetijden zijn via het dashboard aanpasbaar"
  - "PHPUnit groen, PHPStan schoon, Nuxt typecheck schoon"
---

Zie `artifacts/current/plan.md` voor de uitwerking.
