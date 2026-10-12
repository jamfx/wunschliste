<?php $claimDelayMinutes = (int) round(claim_finalize_delay_seconds() / 60); ?>
<div style="max-width:760px;margin-inline:auto;">

    <h1 style="margin-bottom:.5rem;">
        <i class="fa-solid fa-circle-question" aria-hidden="true" style="color:var(--primary-color);margin-right:.4rem;"></i>
        Anleitung &amp; FAQ
    </h1>
    <p class="text-muted" style="margin-bottom:.5rem;">
        Wie <?= h(site_title()) ?> funktioniert – für <strong>Eventersteller</strong> (die eine Liste anlegen und
        verwalten) und <strong>Gäste</strong> (die sich eintragen, was sie mitbringen).
    </p>
    <p class="text-muted text-sm" style="margin-bottom:1.75rem;">
        Kein Account, keine App, keine Installation nötig – alles läuft direkt im Browser über einen Link.
    </p>

    <!-- ============================================================ -->
    <!-- Grundprinzip                                                  -->
    <!-- ============================================================ -->
    <div class="card" style="margin-bottom:1.5rem;">
        <h2 style="font-size:1.125rem;">
            <i class="fa-solid fa-lightbulb" aria-hidden="true" style="color:var(--primary-color);"></i>
            Grundprinzip
        </h2>
        <p style="margin-bottom:1rem;">
            Für jedes Event gibt es eine gemeinsame Liste von Mitbringseln (z.&nbsp;B. „Nudelsalat“, „Getränke“,
            „Zelt“). Gäste tragen über einen Link ein, was sie mitbringen – ohne sich zu registrieren oder
            anzumelden. So sieht auf einen Blick jeder, was schon abgedeckt ist und was noch fehlt.
        </p>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Rolle</th><th>Was sie tun kann</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><i class="fa-solid fa-user-pen" aria-hidden="true"></i> Eventersteller</td>
                        <td>Legt das Event an, pflegt die Liste, sieht alle Details, exportiert PDF</td>
                    </tr>
                    <tr>
                        <td><i class="fa-solid fa-user-group" aria-hidden="true"></i> Gast</td>
                        <td>Sieht die Liste, trägt sich für Mitbringsel ein, kann eigene Vorschläge machen</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- FAQ                                                           -->
    <!-- ============================================================ -->
    <h2 style="font-size:1.25rem;margin-bottom:.875rem;">
        <i class="fa-solid fa-circle-question" aria-hidden="true" style="color:var(--primary-color);"></i>
        Häufige Fragen
    </h2>

    <?php
    $faq = [
        [
            'q' => 'Muss ich mich registrieren, um mitzumachen?',
            'a' => 'Nein. Weder als Ersteller noch als Gast ist ein Konto nötig.',
        ],
        [
            'q' => 'Muss ich beim Anlegen eine E-Mail-Adresse angeben?',
            'a' => 'Nein, sie ist optional. Ohne Mailadresse funktioniert die Liste genauso, es gibt dann aber keine Benachrichtigungen (z.&nbsp;B. bei neuer Zusage oder wenn die Liste komplett ist). Wer beim Anlegen keine Adresse einträgt, sieht dazu einen Warnhinweis und kann die Adresse jederzeit später über „Einstellungen bearbeiten“ nachtragen.',
        ],
        [
            'q' => 'Ich habe den Verwaltungslink meines Events verloren – was nun?',
            'a' => 'Da es keinen Login gibt, kann der Verwaltungslink nicht zurückgesetzt oder erneut zugesendet werden, wenn er verloren geht. Link daher gut aufbewahren (z.&nbsp;B. als Lesezeichen oder in der Erstellungs-Bestätigung, die direkt nach dem Anlegen angezeigt wird).',
        ],
        [
            'q' => 'Kann ich mich als Gast wieder aus der Liste austragen?',
            'a' => 'Ja, in beiden Modi: Jeder Gast kann seinen eigenen Eintrag jederzeit über „Übernahme zurücknehmen“ selbst wieder freigeben. Innerhalb der ersten ' . $claimDelayMinutes . '&nbsp;Minuten nach dem Eintragen (Bedenkzeit) ist der Ersteller ohnehin noch nicht benachrichtigt worden.',
        ],
        [
            'q' => 'Sehen andere Gäste meinen Namen?',
            'a' => 'Das hängt von der Einstellung „Namen der Gäste sichtbar“ des Eventerstellers ab. Ist sie deaktiviert, sehen andere Gäste nur die Anzahl der Zusagen, keine Namen.',
        ],
        [
            'q' => 'Was passiert, wenn ein Mitbringsel schon komplett vergeben ist?',
            'a' => 'Es wird als „vollständig“ markiert und kann nicht mehr zusätzlich beansprucht werden. Im Geschenkmodus wird es zusätzlich für alle außer den eigenen Teilnehmern ausgeblendet.',
        ],
        [
            'q' => 'Wie lange bleibt meine Liste online?',
            'a' => 'Bis zum eingestellten automatischen Löschdatum (Veranstaltungsdatum bzw. Erstellungsdatum + gewählte Anzahl Tage, Standard 30, maximal 365). Der Eventersteller kann diesen Zeitraum jederzeit über „Einstellungen bearbeiten“ anpassen.',
        ],
        [
            'q' => 'Kann ich die Liste ausdrucken oder offline mitnehmen?',
            'a' => 'Ja, über den PDF-Export – sowohl in der Verwaltungsansicht als auch in der Gäste-Ansicht verfügbar.',
        ],
        [
            'q' => 'Ist die Liste passwortgeschützt?',
            'a' => 'Nur, wenn der Eventersteller beim Anlegen (oder später) ein Zugangspasswort vergeben hat. Standardmäßig reicht der Link allein.',
        ],
    ];
    ?>
    <div style="margin-bottom:1.75rem;">
        <?php foreach ($faq as $item): ?>
        <details class="card" style="padding:0;margin-bottom:.625rem;">
            <summary style="padding:.875rem 1.125rem;cursor:pointer;font-weight:600;list-style:none;display:flex;align-items:center;gap:.5rem;">
                <?= $item['q'] /* fest im Code hinterlegt, kein Benutzer-Input */ ?>
                <i class="fa-solid fa-chevron-down" aria-hidden="true" style="margin-left:auto;font-size:.75rem;color:var(--text-muted);"></i>
            </summary>
            <div style="padding:0 1.125rem 1.125rem;color:var(--text-muted);">
                <?= $item['a'] ?>
            </div>
        </details>
        <?php endforeach; ?>
    </div>

    <!-- ============================================================ -->
    <!-- Für Eventersteller                                            -->
    <!-- ============================================================ -->
    <h2 style="font-size:1.25rem;margin-bottom:.875rem;">
        <i class="fa-solid fa-user-pen" aria-hidden="true" style="color:var(--primary-color);"></i>
        Für Eventersteller
    </h2>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Ein Event anlegen</h3>
        <ol style="padding-left:1.25rem;line-height:1.75;">
            <li>Auf der Startseite ein neues Event erstellen.</li>
            <li>Pflichtangabe: <strong>Titel</strong>.</li>
            <li>Optional: Beschreibung (mit einfachem Markdown: <code>**fett**</code>, <code>*kursiv*</code>,
                <code>[Text](https://…)</code>, Listen mit <code>- </code>), Datum der Veranstaltung, <strong>E-Mail-Adresse</strong> (für
                Benachrichtigungen – ohne Mailadresse funktioniert das Event genauso, es gibt dann aber keine
                Benachrichtigungen).</li>
            <li>Weitere Einstellungen lassen sich im Bereich „Einstellungen“ aufklappen.</li>
            <li>Nach dem Absenden wird das Event sofort angelegt – es gibt keine Bestätigung per E-Mail und keinen
                Login. Stattdessen erhält man zwei geheime Links.</li>
        </ol>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-link" aria-hidden="true"></i> Die beiden Links: Verwaltung vs. Gäste-Ansicht</h3>
        <p style="margin-bottom:.75rem;">Nach dem Erstellen werden zwei unterschiedliche Links angezeigt:</p>
        <ul style="padding-left:1.25rem;line-height:1.75;margin-bottom:.75rem;">
            <li><strong>Verwaltungslink</strong> (<code>.../manage</code>) – für den Eventersteller. Damit werden
                Mitbringsel hinzugefügt, bearbeitet, gelöscht, Vorschläge freigegeben und die Einstellungen
                geändert. <strong>Diesen Link nicht an Gäste weitergeben!</strong></li>
            <li><strong>Gäste-Link</strong> (<code>.../g/...</code>) – der Link, der an alle Gäste verschickt wird
                (z.&nbsp;B. per WhatsApp, E-Mail oder QR-Code). Darüber können Gäste sich eintragen, aber nichts an
                den Grundeinstellungen ändern.</li>
        </ul>
        <div class="alert alert-info" role="note" style="margin-bottom:0;">
            <strong><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Wichtig:</strong>
            Beide Links enthalten ein langes, zufälliges Token statt eines Logins. Wer den Verwaltungslink kennt,
            kann das Event verwalten – daher sollte dieser Link vertraulich behandelt werden. Ein Lesezeichen oder
            eine Notiz mit dem Link empfiehlt sich, da es kein Passwort-Reset-Verfahren gibt.
        </div>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-list-check" aria-hidden="true"></i> Mitbringsel verwalten</h3>
        <p style="margin-bottom:.5rem;">Auf der Verwaltungsseite lässt sich pro Mitbringsel festlegen:</p>
        <ul style="padding-left:1.25rem;line-height:1.75;margin-bottom:.75rem;">
            <li><strong>Name</strong> (z.&nbsp;B. „Kartoffelsalat“)</li>
            <li><strong>Menge</strong> – wie viele Exemplare/Portionen gebraucht werden (z.&nbsp;B. 3×&nbsp;Kuchen).
                Gäste können sich dann jeweils für eine Teilmenge eintragen, bis die Menge voll ist.</li>
        </ul>
        <p style="margin-bottom:0;">
            Jedes Mitbringsel zeigt an, wie viel bereits übernommen wurde (z.&nbsp;B. „2/3“), inklusive der Namen der
            Gäste. Einträge lassen sich jederzeit bearbeiten oder löschen. Die Menge kann nicht unter die bereits
            zugesagte Anzahl reduziert werden.
        </p>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-inbox" aria-hidden="true"></i> Vorschläge von Gästen freigeben</h3>
        <p style="margin-bottom:.5rem;">
            Wenn die Einstellung <strong>„Gäste dürfen Mitbringsel vorschlagen“</strong> aktiviert ist, können Gäste
            eigene Ideen einreichen. Diese erscheinen auf der Verwaltungsseite als <strong>Vorschläge</strong> und
            müssen erst bestätigt werden, bevor sie für alle Gäste sichtbar werden:
        </p>
        <ul style="padding-left:1.25rem;line-height:1.75;margin-bottom:0;">
            <li><strong>Freigeben</strong> → wird zu einem normalen Mitbringsel in der Liste</li>
            <li><strong>Ablehnen</strong> → wird gelöscht</li>
        </ul>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-sliders" aria-hidden="true"></i> Einstellungen im Detail</h3>
        <p class="text-muted text-sm" style="margin-bottom:.875rem;">
            Beim Anlegen oder späteren Bearbeiten eines Events stehen folgende Optionen zur Verfügung:
        </p>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Einstellung</th><th>Bedeutung</th></tr></thead>
                <tbody>
                    <tr>
                        <td>Namen der Gäste sichtbar</td>
                        <td>Wenn aktiv, sehen alle Gäste, wer welches Mitbringsel übernommen hat. Wenn inaktiv,
                            sehen Gäste nur die Anzahl („3 Personen bringen mit“), keine Namen.</td>
                    </tr>
                    <tr>
                        <td>Gäste dürfen Mitbringsel vorschlagen</td>
                        <td>Erlaubt Gästen, eigene Ideen einzureichen (müssen vom Ersteller freigegeben werden).</td>
                    </tr>
                    <tr>
                        <td>Benachrichtigung bei neuer Zusage</td>
                        <td>E-Mail an den Ersteller, sobald sich ein Gast für ein Mitbringsel einträgt (nur wenn eine
                            E-Mail-Adresse hinterlegt ist). Wird nicht sofort verschickt, sondern erst nach einer
                            <?= $claimDelayMinutes ?>-minütigen Bedenkzeit (vom Betreiber der Seite einstellbar) – so
                            bleibt dem Gast Zeit, sich umzuentscheiden (siehe „Eigenen Eintrag zurückziehen“).</td>
                    </tr>
                    <tr>
                        <td>Benachrichtigung bei vollständiger Liste</td>
                        <td>E-Mail an den Ersteller, sobald alle Mitbringsel vollständig abgedeckt sind (nur wenn
                            eine E-Mail-Adresse hinterlegt ist).</td>
                    </tr>
                    <tr>
                        <td>Automatisches Löschen nach X&nbsp;Tagen</td>
                        <td>Legt fest, wie lange das Event nach dem Veranstaltungsdatum (bzw. nach Erstellung, falls
                            kein Datum gesetzt ist) online bleibt, bevor es automatisch gelöscht wird (1–365 Tage,
                            Standard 30).</td>
                    </tr>
                    <tr>
                        <td>Geschenkmodus</td>
                        <td>Siehe Abschnitt „Geschenkmodus“ weiter unten.</td>
                    </tr>
                    <tr>
                        <td>Zugangspasswort</td>
                        <td>Optionaler Schutz der Gäste-Ansicht mit einem Passwort (4–50 Zeichen). Ohne das Passwort
                            kann die Liste über den Gäste-Link nicht eingesehen werden.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Event nachträglich bearbeiten</h3>
        <p style="margin-bottom:0;">
            Über „Einstellungen bearbeiten“ auf der Verwaltungsseite lassen sich Titel, Beschreibung, Datum,
            E-Mail-Adresse und alle oben genannten Einstellungen jederzeit ändern – auch nachdem Gäste sich bereits
            eingetragen haben. Wer beim Anlegen keine E-Mail-Adresse hinterlegt hat, kann sie hier jederzeit
            nachtragen, um Benachrichtigungen zu aktivieren. Ein bestehendes Zugangspasswort bleibt erhalten, wenn
            beim Bearbeiten kein neues eingegeben wird; es kann auch gezielt entfernt werden.
        </p>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Liste als PDF exportieren</h3>
        <p style="margin-bottom:.5rem;">
            Sowohl über die Verwaltungsseite als auch über die Gäste-Ansicht lässt sich die Liste als PDF
            herunterladen:
        </p>
        <ul style="padding-left:1.25rem;line-height:1.75;margin-bottom:0;">
            <li>Die <strong>Admin-PDF</strong> (aus der Verwaltung) enthält auch noch offene Vorschläge und alle
                Details.</li>
            <li>Die <strong>Gäste-PDF</strong> zeigt nur freigegebene Mitbringsel, entsprechend der Einstellung
                „Namen sichtbar“. Im Geschenkmodus werden bereits vollständig übernommene Geschenke (außer den
                eigenen) ebenfalls ausgeblendet, damit die Überraschung erhalten bleibt.</li>
        </ul>
    </div>

    <div class="card" style="margin-bottom:1.75rem;">
        <h3><i class="fa-regular fa-clock" aria-hidden="true"></i> Ablauf &amp; automatisches Löschen</h3>
        <p style="margin-bottom:0;">
            Jedes Event hat ein Ablaufdatum, das sich aus dem Veranstaltungsdatum (oder dem Erstellungsdatum, falls
            kein Datum angegeben wurde) plus der eingestellten Anzahl an Tagen ergibt. Nach Ablauf wird das Event
            inklusive aller Daten automatisch gelöscht. Bis dahin ist die Liste über beide Links weiterhin
            erreichbar (nach Ablauf erscheint stattdessen ein Hinweis, dass das Event abgelaufen ist).
        </p>
    </div>

    <!-- ============================================================ -->
    <!-- Für Gäste                                                     -->
    <!-- ============================================================ -->
    <h2 style="font-size:1.25rem;margin-bottom:.875rem;">
        <i class="fa-solid fa-user-group" aria-hidden="true" style="color:var(--primary-color);"></i>
        Für Gäste
    </h2>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-hand-pointer" aria-hidden="true"></i> Sich in die Liste eintragen</h3>
        <ol style="padding-left:1.25rem;line-height:1.75;margin-bottom:.75rem;">
            <li>Den vom Eventersteller erhaltenen <strong>Gäste-Link</strong> öffnen (per WhatsApp, E-Mail,
                QR-Code o.&nbsp;Ä.).</li>
            <li>Die Liste der noch offenen Mitbringsel wird angezeigt, inklusive eines Fortschrittsbalkens.</li>
            <li>Bei einem gewünschten Mitbringsel den eigenen Namen eintragen (und ggf. Menge, falls mehrere
                Exemplare gebraucht werden) und absenden.</li>
            <li>Fertig – <strong>keine Registrierung, kein Passwort, keine E-Mail-Adresse nötig</strong> (außer im
                Geschenkmodus optional für eine Benachrichtigung).</li>
        </ol>
        <p class="text-muted text-sm" style="margin-bottom:0;">
            Je nach Einstellung des Erstellers sind entweder die Namen aller Gäste sichtbar oder nur, wie viele
            Personen sich bereits eingetragen haben.
        </p>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Eigenen Eintrag zurückziehen</h3>
        <p style="margin-bottom:0;">
            Nach dem Eintragen hat jeder Gast <strong><?= $claimDelayMinutes ?>&nbsp;Minuten Bedenkzeit</strong>
            (vom Betreiber der Seite einstellbar): Die Benachrichtigungs-E-Mail an den Eventersteller wird erst nach
            Ablauf dieser Frist verschickt. In dieser Zeit – und auch danach, jederzeit – kann der Gast seinen
            eigenen Eintrag über den Button „Übernahme zurücknehmen“ selbst wieder freigeben. Das funktioniert
            browserbasiert, ohne Login, solange derselbe Browser/dasselbe Gerät verwendet wird. Ist die Bedenkzeit
            bereits abgelaufen, erscheint beim Zurücknehmen ein Hinweis, dass der Ersteller eventuell schon
            informiert wurde. Das gilt gleichermaßen im normalen Modus wie im Geschenkmodus.
        </p>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-lightbulb" aria-hidden="true"></i> Einen eigenen Vorschlag machen</h3>
        <p style="margin-bottom:0;">
            Falls der Eventersteller es erlaubt hat, kann am Ende der Liste ein neues Mitbringsel vorgeschlagen
            werden (Name + eigener Name). Der Vorschlag erscheint erst in der öffentlichen Liste, nachdem der
            Eventersteller ihn freigegeben hat.
        </p>
    </div>

    <div class="card" style="margin-bottom:1.75rem;">
        <h3><i class="fa-solid fa-lock" aria-hidden="true"></i> Zugangspasswort</h3>
        <p style="margin-bottom:0;">
            Falls der Eventersteller ein Zugangspasswort vergeben hat, wird beim Öffnen des Gäste-Links zunächst
            danach gefragt. Ohne das korrekte Passwort ist die Liste nicht einsehbar. Das Passwort wird vom
            Eventersteller separat mitgeteilt (z.&nbsp;B. zusammen mit dem Link).
        </p>
    </div>

    <!-- ============================================================ -->
    <!-- Geschenkmodus                                                 -->
    <!-- ============================================================ -->
    <div class="card" style="margin-bottom:1.75rem;background:var(--success-bg);border-color:var(--success);">
        <h2 style="font-size:1.125rem;">
            <i class="fa-solid fa-gift" aria-hidden="true" style="color:var(--success);"></i>
            Geschenkmodus (Gift Mode)
        </h2>
        <p style="margin-bottom:.75rem;">
            Der Geschenkmodus eignet sich für Anlässe wie Geburtstage oder Hochzeiten, bei denen Geschenke eine
            Überraschung bleiben sollen – anders als bei einer offenen Grillparty-Liste. Ist er aktiviert, ändert
            sich das Verhalten der Gäste-Ansicht:
        </p>
        <ul style="padding-left:1.25rem;line-height:1.8;margin-bottom:0;">
            <li><strong>Vollständig übernommene Geschenke werden für alle anderen Gäste unsichtbar.</strong> Nur die
                Person(en), die es übernommen haben, sehen ihren eigenen Eintrag weiterhin (mit der Markierung
                „Von dir übernommen“).</li>
            <li>Bei Geschenken, die von <strong>mehreren Personen gemeinsam</strong> finanziert/organisiert werden
                (Menge&nbsp;&gt;&nbsp;1), kann jeder Teilnehmer freiwillig eine E-Mail-Adresse hinterlassen. Sobald
                alle Plätze besetzt sind, erhalten alle, die eine Adresse angegeben haben, automatisch eine E-Mail
                mit den Namen der anderen Teilnehmer, damit sie sich untereinander abstimmen können (z.&nbsp;B. wer
                das Geschenk kauft). Die E-Mail-Adresse wird ausschließlich für diese eine Benachrichtigung
                verwendet und danach nicht weiter gespeichert.</li>
            <li>Ein Gast kann sein eigenes, vollständig übernommenes Geschenk jederzeit wieder freigeben
                („Zurückziehen“), falls sich die Pläne ändern (siehe „Eigenen Eintrag zurückziehen“ für die
                <?= $claimDelayMinutes ?>-minütige Bedenkzeit vor dem Mailversand an den Ersteller). Bei bereits
                benachrichtigten Mitorganisatoren bzw. dem Ersteller erscheint dabei jeweils ein eigener Hinweis,
                dass diese eventuell schon informiert wurden.</li>
            <li>Auch der <strong>PDF-Export der Gäste-Ansicht</strong> respektiert den Geschenkmodus: bereits
                vergebene Geschenke (außer den eigenen) tauchen dort nicht auf.</li>
            <li>Der Eventersteller selbst sieht auf der Verwaltungsseite immer die vollständige Liste mit allen
                Details, unabhängig vom Geschenkmodus.</li>
        </ul>
    </div>

    <!-- ============================================================ -->
    <!-- Datenschutz & Sicherheit                                      -->
    <!-- ============================================================ -->
    <div class="card" style="margin-bottom:1rem;">
        <h2 style="font-size:1.125rem;">
            <i class="fa-solid fa-shield-halved" aria-hidden="true" style="color:var(--primary-color);"></i>
            Datenschutz &amp; Sicherheit
        </h2>
        <ul style="padding-left:1.25rem;line-height:1.8;margin-bottom:0;">
            <li>Es ist <strong>keine Registrierung</strong> erforderlich, weder für Ersteller noch für Gäste.</li>
            <li>Der Zugriff erfolgt über lange, zufällige Links (Tokens) statt über Benutzerkonten. Wer einen Link
                kennt, hat Zugriff auf die jeweilige Ansicht – Links daher nicht öffentlich teilen, wenn die Liste
                privat bleiben soll (zusätzlich per Zugangspasswort möglich).</li>
            <li>Von Gästen werden nur der eingetragene Name (und im Geschenkmodus optional eine E-Mail-Adresse für
                die Koordinationsmail) gespeichert – letztere wird nach Versand der Benachrichtigung nicht weiter
                benötigt.</li>
            <li>Events werden nach Ablauf automatisch inklusive aller zugehörigen Daten gelöscht.</li>
        </ul>
    </div>

</div>
