/*
 * The admin dashboard's poll: the one script in the application
 * (System_Plan.md §3, API_Design.md §7.5, web/docs/DESIGN.md §§7, 9).
 *
 * The server owns every number and status; this only draws them. A failed
 * refresh keeps the last known data and says so. It never shows zeros and never
 * claims a bin is offline. Numbers update in place, with no animation.
 */
const root = document.querySelector('[data-dashboard]');

if (root) {
    const { summaryUrl, binUrl, redemptionsUrl, reviewUrl, timeZone } = root.dataset;
    const pollMs = Number(root.dataset.pollMs);

    const status = root.querySelector('[data-dashboard-status]');
    const binsTable = root.querySelector('[data-bins-table]');
    const binsBody = binsTable.querySelector('tbody');
    const binsEmpty = root.querySelector('[data-bins-empty]');
    const binTemplate = root.querySelector('[data-bin-template]');
    const generatedAt = root.querySelector('[data-generated-at]');
    const attention = root.querySelector('[data-attention]');

    const timeParts = new Intl.DateTimeFormat('en-US', {
        timeZone,
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });

    // The rest of this block repeats App\Support\Display, for the values redrawn here.
    const formatTime = (iso) => {
        const part = Object.fromEntries(timeParts.formatToParts(new Date(iso)).map(({ type, value }) => [type, value]));

        return `${part.day} ${part.month} ${part.year}, ${part.hour}:${part.minute}${part.dayPeriod.toLowerCase()}`;
    };

    const formatNumber = (value, digits) =>
        value.toLocaleString('en-US', { minimumFractionDigits: digits, maximumFractionDigits: digits });

    const formatGrams = (grams) => {
        const formatted = formatNumber(grams, 2);

        return `${formatted.endsWith('0') ? formatted.slice(0, -1) : formatted} g`;
    };

    const formats = {
        grams: formatGrams,
        points: (points) => `${formatNumber(points, 0)} pts`,
    };

    const formatPercent = (percent) => `${formatNumber(percent, 2).replace(/\.?0+$/, '')}%`;

    const sentenceCase = (text) => text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();

    const statusTones = { OK: 'pill-ok', FULL: 'pill-warn' };
    const statusLabels = { OK: 'OK', FULL: 'Full' };

    const faultLabel = (faultCode) => sentenceCase(faultCode.replaceAll('_', ' '));

    const binLink = (bin) => binUrl.replace(':id', bin.id);

    const counted = (count, noun) => `${count} ${noun}${count === 1 ? '' : 's'}`;

    const attentionItem = ([href, text]) => {
        const item = document.createElement('li');
        const link = document.createElement('a');

        link.href = href;
        link.textContent = text;
        item.append(link);

        return item;
    };

    const attentionItems = (summary) => [
        [redemptionsUrl, `${counted(summary.pending_redemptions, 'redemption')} waiting to be fulfilled`],
        ...summary.bins.flatMap((bin) => [
            ...(bin.status === 'FULL' ? [[binLink(bin), `${bin.name} is full (${formatPercent(bin.fill_percent)})`]] : []),
            ...(bin.fault_code ? [[binLink(bin), `${bin.name}: ${faultLabel(bin.fault_code)}`]] : []),
        ]),
        [reviewUrl, `${counted(summary.sessions_awaiting_review, 'session')} awaiting review`],
    ];

    // textContent, never innerHTML: bin names are typed in by admins.
    const binRow = (bin) => {
        const row = binTemplate.content.cloneNode(true);
        const field = (name) => row.querySelector(`[data-bin="${name}"]`);
        const hasReading = bin.fill_percent !== null;

        field('link').textContent = bin.name;
        field('link').href = binLink(bin);

        field('reading').hidden = !hasReading;
        field('no-reading').hidden = hasReading;
        field('no-status').hidden = hasReading;
        field('no-panel').hidden = hasReading;

        if (hasReading) {
            field('meter').style.width = `${Math.min(100, Math.max(0, bin.fill_percent))}%`;
            field('percent').textContent = formatPercent(bin.fill_percent);

            field('status').hidden = false;
            field('status').className = `pill ${statusTones[bin.status] ?? ''}`;
            field('status').textContent = statusLabels[bin.status] ?? bin.status;

            field('panel-open').hidden = !bin.panel_open;
            field('panel-closed').hidden = bin.panel_open;
        }

        field('fault').hidden = !bin.fault_code;
        field('no-fault').hidden = Boolean(bin.fault_code);
        field('no-fault').textContent = hasReading ? 'None' : '—';

        if (bin.fault_code) {
            field('fault-label').textContent = faultLabel(bin.fault_code);
            field('fault-code').textContent = bin.fault_code;
        }

        field('last-seen').textContent = bin.last_seen_at === null ? 'Never' : formatTime(bin.last_seen_at);

        return row;
    };

    const render = (summary) => {
        generatedAt.textContent = formatTime(summary.generated_at);

        root.querySelectorAll('[data-summary]').forEach((element) => {
            const value = element.dataset.summary.split('.').reduce((current, key) => current[key], summary);
            const format = formats[element.dataset.format];

            element.textContent = format ? format(value) : value;
        });

        attention.replaceChildren(...attentionItems(summary).map(attentionItem));

        binsEmpty.hidden = summary.bins.length > 0;
        binsTable.hidden = summary.bins.length === 0;
        binsBody.replaceChildren(...summary.bins.map(binRow));
    };

    let failed = false;

    const showFailure = () => {
        if (failed) {
            return;
        }

        failed = true;

        const message = document.createElement('div');
        message.className = 'feedback';
        message.textContent = `Could not refresh. Showing the last known data from ${generatedAt.textContent} (${timeZone}).`;
        status.replaceChildren(message);
    };

    const showRecovery = () => {
        if (failed) {
            failed = false;
            status.replaceChildren();
        }
    };

    // One request at a time: the next is scheduled only after this one ends.
    // Skipped while the tab is hidden, so a forgotten tab doesn't keep the Pi busy.
    const poll = async () => {
        if (!document.hidden) {
            try {
                const response = await fetch(summaryUrl, { headers: { Accept: 'application/json' } });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                render(await response.json());
                showRecovery();
            } catch {
                showFailure();
            }
        }

        setTimeout(poll, pollMs);
    };

    setTimeout(poll, pollMs);
}
