<?php $claimDelayMinutes = (int) round(claim_finalize_delay_seconds() / 60); ?>
<div style="max-width:760px;margin-inline:auto;">

    <h1 style="margin-bottom:.5rem;">
        <i class="fa-solid fa-circle-question" aria-hidden="true" style="color:var(--primary-color);margin-right:.4rem;"></i>
        Guide &amp; FAQ
    </h1>
    <p class="text-muted" style="margin-bottom:.5rem;">
        How <?= h(site_title()) ?> works – for <strong>event organizers</strong> (who create and manage a list)
        and <strong>guests</strong> (who sign up for what they'll bring).
    </p>
    <p class="text-muted text-sm" style="margin-bottom:1.75rem;">
        No account, no app, no installation needed – everything runs right in the browser via a link.
    </p>

    <!-- ============================================================ -->
    <!-- Basics                                                        -->
    <!-- ============================================================ -->
    <div class="card" style="margin-bottom:1.5rem;">
        <h2 style="font-size:1.125rem;">
            <i class="fa-solid fa-lightbulb" aria-hidden="true" style="color:var(--primary-color);"></i>
            The Basics
        </h2>
        <p style="margin-bottom:1rem;">
            Every event has a shared list of items to bring (e.g. "potato salad", "drinks", "tent"). Guests use a
            link to sign up for what they'll bring – no registration or login required. That way everyone can see
            at a glance what's already covered and what's still missing.
        </p>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Role</th><th>What they can do</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><i class="fa-solid fa-user-pen" aria-hidden="true"></i> Event organizer</td>
                        <td>Creates the event, maintains the list, sees all details, exports a PDF</td>
                    </tr>
                    <tr>
                        <td><i class="fa-solid fa-user-group" aria-hidden="true"></i> Guest</td>
                        <td>Views the list, signs up for items, can suggest new items</td>
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
        Frequently Asked Questions
    </h2>

    <?php
    $faq = [
        [
            'q' => 'Do I need to register to take part?',
            'a' => 'No. Neither organizers nor guests need an account.',
        ],
        [
            'q' => 'Do I have to enter an email address when creating an event?',
            'a' => 'No, it\'s optional. The list works the same without it, but you won\'t receive any notifications (e.g. on a new claim or once the list is complete). If you leave it blank, you\'ll see a warning first, and you can always add it later via "Edit settings".',
        ],
        [
            'q' => 'I lost my event\'s management link – what now?',
            'a' => 'Since there\'s no login, a lost management link cannot be reset or resent. Keep it somewhere safe (e.g. as a bookmark, or in the confirmation shown right after creating the event).',
        ],
        [
            'q' => 'Can I remove myself from the list as a guest?',
            'a' => 'Yes, in both modes: any guest can withdraw their own entry at any time via "Undo claim". Within the first ' . $claimDelayMinutes . '&nbsp;minutes after signing up (the reconsideration window), the organizer hasn\'t even been notified yet.',
        ],
        [
            'q' => 'Can other guests see my name?',
            'a' => 'That depends on the organizer\'s "Show guest names" setting. If it\'s disabled, other guests only see how many people signed up, not the names.',
        ],
        [
            'q' => 'What happens once an item is fully claimed?',
            'a' => 'It\'s marked as "complete" and can no longer be claimed further. In Gift Mode, it\'s also hidden from everyone except the guest(s) who claimed it.',
        ],
        [
            'q' => 'How long does my list stay online?',
            'a' => 'Until the configured auto-delete date (event date, or creation date if none was set, plus the chosen number of days — 30 by default, 365 max). The organizer can adjust this anytime via "Edit settings".',
        ],
        [
            'q' => 'Can I print the list or take it offline?',
            'a' => 'Yes, via the PDF export – available both in the management view and the guest view.',
        ],
        [
            'q' => 'Is the list password-protected?',
            'a' => 'Only if the organizer set an access password when creating (or later editing) the event. By default, the link alone is enough.',
        ],
    ];
    ?>
    <div style="margin-bottom:1.75rem;">
        <?php foreach ($faq as $item): ?>
        <details class="card" style="padding:0;margin-bottom:.625rem;">
            <summary style="padding:.875rem 1.125rem;cursor:pointer;font-weight:600;list-style:none;display:flex;align-items:center;gap:.5rem;">
                <?= $item['q'] /* fixed copy, not user input */ ?>
                <i class="fa-solid fa-chevron-down" aria-hidden="true" style="margin-left:auto;font-size:.75rem;color:var(--text-muted);"></i>
            </summary>
            <div style="padding:0 1.125rem 1.125rem;color:var(--text-muted);">
                <?= $item['a'] ?>
            </div>
        </details>
        <?php endforeach; ?>
    </div>

    <!-- ============================================================ -->
    <!-- For organizers                                                -->
    <!-- ============================================================ -->
    <h2 style="font-size:1.25rem;margin-bottom:.875rem;">
        <i class="fa-solid fa-user-pen" aria-hidden="true" style="color:var(--primary-color);"></i>
        For Event Organizers
    </h2>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Creating an event</h3>
        <ol style="padding-left:1.25rem;line-height:1.75;">
            <li>Create a new event on the homepage.</li>
            <li>Required: <strong>title</strong>.</li>
            <li>Optional: description (simple Markdown works: <code>**bold**</code>, <code>*italic*</code>,
                <code>[text](https://…)</code>, lists with <code>- </code>), event date, <strong>email address</strong> (for notifications – the event
                works the same without one, you just won't receive any notifications).</li>
            <li>Further options can be expanded under "Settings".</li>
            <li>The event is created immediately on submit – no email confirmation and no login required. Instead
                you receive two secret links.</li>
        </ol>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-link" aria-hidden="true"></i> The two links: management vs. guest view</h3>
        <p style="margin-bottom:.75rem;">After creating the event, two different links are shown:</p>
        <ul style="padding-left:1.25rem;line-height:1.75;margin-bottom:.75rem;">
            <li><strong>Management link</strong> (<code>.../manage</code>) – for the organizer. Used to add, edit
                and delete items, approve suggestions, and change settings. <strong>Never share this link with
                guests!</strong></li>
            <li><strong>Guest link</strong> (<code>.../g/...</code>) – the link sent to all guests (e.g. via
                WhatsApp, email or a QR code). Guests can sign up here, but cannot change any core settings.</li>
        </ul>
        <div class="alert alert-info" role="note" style="margin-bottom:0;">
            <strong><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Important:</strong>
            Both links contain a long, random token instead of a login. Anyone who knows the management link can
            manage the event – so keep it confidential. Bookmarking it or writing it down is recommended, as there
            is no password-reset process.
        </div>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-list-check" aria-hidden="true"></i> Managing items</h3>
        <p style="margin-bottom:.5rem;">On the management page, each item can have:</p>
        <ul style="padding-left:1.25rem;line-height:1.75;margin-bottom:.75rem;">
            <li><strong>Name</strong> (e.g. "potato salad")</li>
            <li><strong>Quantity</strong> – how many units/portions are needed (e.g. 3×&nbsp;cake). Guests can then
                each sign up for part of the quantity until it's fully claimed.</li>
        </ul>
        <p style="margin-bottom:0;">
            Every item shows how much has already been claimed (e.g. "2/3"), including the guests' names. Items
            can be edited or deleted at any time. Quantity cannot be reduced below the amount already claimed.
        </p>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-inbox" aria-hidden="true"></i> Approving guest suggestions</h3>
        <p style="margin-bottom:.5rem;">
            When <strong>"Guests can suggest items"</strong> is enabled, guests can submit their own ideas. These
            appear on the management page as <strong>suggestions</strong> and must be approved before they become
            visible to all guests:
        </p>
        <ul style="padding-left:1.25rem;line-height:1.75;margin-bottom:0;">
            <li><strong>Approve</strong> → becomes a regular item on the list</li>
            <li><strong>Reject</strong> → gets deleted</li>
        </ul>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-sliders" aria-hidden="true"></i> Settings in detail</h3>
        <p class="text-muted text-sm" style="margin-bottom:.875rem;">
            The following options are available when creating or later editing an event:
        </p>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Setting</th><th>What it does</th></tr></thead>
                <tbody>
                    <tr>
                        <td>Show guest names</td>
                        <td>When on, all guests see who claimed which item. When off, guests only see the count
                            ("3 people are bringing this"), not names.</td>
                    </tr>
                    <tr>
                        <td>Guests can suggest items</td>
                        <td>Lets guests submit their own ideas (must be approved by the organizer).</td>
                    </tr>
                    <tr>
                        <td>Notify on new claim</td>
                        <td>Emails the organizer whenever a guest signs up for an item (only if an email address is
                            set). Not sent right away — only after a <?= $claimDelayMinutes ?>-minute reconsideration
                            window (configurable by the site operator), giving the guest time to change their mind
                            (see "Withdrawing your own entry").</td>
                    </tr>
                    <tr>
                        <td>Notify when list is complete</td>
                        <td>Emails the organizer once every item is fully claimed (only if an email address is
                            set).</td>
                    </tr>
                    <tr>
                        <td>Auto-delete after X&nbsp;days</td>
                        <td>Controls how long the event stays online after the event date (or after creation, if
                            no date was set) before it's automatically deleted (1–365 days, default 30).</td>
                    </tr>
                    <tr>
                        <td>Gift Mode</td>
                        <td>See the "Gift Mode" section below.</td>
                    </tr>
                    <tr>
                        <td>Access password</td>
                        <td>Optional password protection for the guest view (4–50 characters). Without the
                            correct password, the list cannot be viewed via the guest link.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Editing an event later</h3>
        <p style="margin-bottom:0;">
            "Edit settings" on the management page lets you change the title, description, date, email address and
            all settings above at any time – even after guests have already signed up. If you didn't set an email
            address when creating the event, you can add it here later to enable notifications. An existing access
            password is kept unless a new one is entered; it can also be removed explicitly.
        </p>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Exporting the list as a PDF</h3>
        <p style="margin-bottom:.5rem;">
            The list can be downloaded as a PDF from both the management page and the guest view:
        </p>
        <ul style="padding-left:1.25rem;line-height:1.75;margin-bottom:0;">
            <li>The <strong>organizer PDF</strong> (from the management page) also includes open suggestions and
                all details.</li>
            <li>The <strong>guest PDF</strong> only shows approved items, respecting the "show names" setting. In
                Gift Mode, already fully-claimed gifts (other than the guest's own) are hidden there too, to keep
                the surprise.</li>
        </ul>
    </div>

    <div class="card" style="margin-bottom:1.75rem;">
        <h3><i class="fa-regular fa-clock" aria-hidden="true"></i> Expiry &amp; automatic deletion</h3>
        <p style="margin-bottom:0;">
            Every event has an expiry date, calculated from the event date (or the creation date, if no date was
            set) plus the configured number of days. After expiry, the event and all its data are deleted
            automatically. Until then, the list remains reachable via both links (after expiry, a notice is shown
            instead).
        </p>
    </div>

    <!-- ============================================================ -->
    <!-- For guests                                                    -->
    <!-- ============================================================ -->
    <h2 style="font-size:1.25rem;margin-bottom:.875rem;">
        <i class="fa-solid fa-user-group" aria-hidden="true" style="color:var(--primary-color);"></i>
        For Guests
    </h2>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-hand-pointer" aria-hidden="true"></i> Signing up on the list</h3>
        <ol style="padding-left:1.25rem;line-height:1.75;margin-bottom:.75rem;">
            <li>Open the <strong>guest link</strong> received from the organizer (via WhatsApp, email, QR code,
                etc.).</li>
            <li>The list of open items is shown, including a progress bar.</li>
            <li>For an item you'd like to bring, enter your name (and, if applicable, a quantity) and submit.</li>
            <li>Done – <strong>no registration, no password, no email address required</strong> (except optionally
                in Gift Mode for a notification).</li>
        </ol>
        <p class="text-muted text-sm" style="margin-bottom:0;">
            Depending on the organizer's settings, either all guest names are visible, or just how many people have
            signed up.
        </p>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Withdrawing your own entry</h3>
        <p style="margin-bottom:0;">
            After signing up, every guest gets a <strong><?= $claimDelayMinutes ?>-minute reconsideration
            window</strong> (configurable by the site operator): the notification e-mail to the event organizer is
            only sent once that window has passed. During that time – and afterward, at any time – a guest can
            release their own entry themselves via the "Undo claim" button. This works based on the browser
            session, without a login, as long as the same browser/device is used. If the window has already passed,
            undoing shows a hint that the organizer may already have been notified. This applies equally to normal
            mode and Gift Mode.
        </p>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <h3><i class="fa-solid fa-lightbulb" aria-hidden="true"></i> Suggesting your own item</h3>
        <p style="margin-bottom:0;">
            If the organizer has allowed it, a new item can be suggested at the bottom of the list (item name +
            your name). The suggestion only appears in the public list once the organizer approves it.
        </p>
    </div>

    <div class="card" style="margin-bottom:1.75rem;">
        <h3><i class="fa-solid fa-lock" aria-hidden="true"></i> Access password</h3>
        <p style="margin-bottom:0;">
            If the organizer set an access password, you'll be asked for it when opening the guest link. Without
            the correct password, the list cannot be viewed. The password is communicated separately by the
            organizer (e.g. together with the link).
        </p>
    </div>

    <!-- ============================================================ -->
    <!-- Gift Mode                                                     -->
    <!-- ============================================================ -->
    <div class="card" style="margin-bottom:1.75rem;background:var(--success-bg);border-color:var(--success);">
        <h2 style="font-size:1.125rem;">
            <i class="fa-solid fa-gift" aria-hidden="true" style="color:var(--success);"></i>
            Gift Mode
        </h2>
        <p style="margin-bottom:.75rem;">
            Gift Mode is designed for occasions like birthdays or weddings, where gifts are meant to stay a
            surprise – unlike an open list for a barbecue party. When enabled, the guest view behaves differently:
        </p>
        <ul style="padding-left:1.25rem;line-height:1.8;margin-bottom:0;">
            <li><strong>Fully-claimed gifts become invisible to all other guests.</strong> Only the person(s) who
                claimed it still see their own entry (marked "Claimed by you").</li>
            <li>For gifts claimed by <strong>multiple people together</strong> (quantity&nbsp;&gt;&nbsp;1), each
                participant can optionally leave an email address. Once all slots are filled, everyone who provided
                an address automatically receives an email with the names of the other participants, so they can
                coordinate (e.g. who actually buys the gift). The email address is used only for this one
                notification and is not stored afterward.</li>
            <li>A guest can release their own fully-claimed gift at any time ("Undo claim") if plans change (see
                "Withdrawing your own entry" for the <?= $claimDelayMinutes ?>-minute reconsideration window before
                the organizer is e-mailed). If co-participants and/or the organizer were likely already notified, a
                separate hint is shown for each.</li>
            <li>The <strong>guest-view PDF export</strong> also respects Gift Mode: already-claimed gifts (other
                than the guest's own) don't appear there either.</li>
            <li>The organizer always sees the full list with all details on the management page, regardless of
                Gift Mode.</li>
        </ul>
    </div>

    <!-- ============================================================ -->
    <!-- Privacy & Security                                            -->
    <!-- ============================================================ -->
    <div class="card" style="margin-bottom:1rem;">
        <h2 style="font-size:1.125rem;">
            <i class="fa-solid fa-shield-halved" aria-hidden="true" style="color:var(--primary-color);"></i>
            Privacy &amp; Security
        </h2>
        <ul style="padding-left:1.25rem;line-height:1.8;margin-bottom:0;">
            <li><strong>No registration</strong> is required, neither for organizers nor guests.</li>
            <li>Access is granted via long, random links (tokens) instead of user accounts. Anyone who knows a
                link has access to the corresponding view – so don't share links publicly if the list should stay
                private (an access password provides additional protection).</li>
            <li>Only the name a guest enters is stored (and, in Gift Mode, optionally an email address for the
                coordination email) – the latter is no longer needed once that notification has been sent.</li>
            <li>Events and all associated data are automatically deleted once they expire.</li>
        </ul>
    </div>

</div>
