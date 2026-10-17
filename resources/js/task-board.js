function initTaskBoard() {
    let board = document.querySelector('[data-task-board]');
    const message = document.querySelector('[data-board-message]');

    if (!board || !message) return;

    let busy = false;
    let draggedCard = null;

    const announce = (text) => {
        message.textContent = text;
    };

    const clearHighlights = () => {
        board.querySelectorAll('[data-board-column]').forEach((column) => {
            column.classList.remove('ring-2', 'ring-indigo-400');
        });
    };

    const prepareBoard = () => {
        board.querySelectorAll('[data-task-drag-handle]').forEach((handle) => {
            handle.hidden = false;
            handle.draggable = true;
        });
    };

    const setBusy = (value) => {
        busy = value;
        board.setAttribute('aria-busy', String(value));

        board.querySelectorAll('select, button').forEach((control) => {
            control.disabled = value;
        });

        board.querySelectorAll('[data-task-drag-handle]').forEach((handle) => {
            handle.draggable = !value;
        });
    };

    async function moveTask(card, status) {
        if (busy || status === card.dataset.taskStatus) return;

        const form = card.querySelector('[data-task-status-form]');
        const taskId = card.dataset.taskId;
        let saved = false;
        let failureMessage =
            'Could not confirm the change. Reload the board before retrying.';

        setBusy(true);
        clearHighlights();
        announce('Saving task status…');

        try {
            const response = await fetch(form.action, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': form.elements.namedItem('_token').value,
                },
                body: JSON.stringify({
                    status,
                    lock_version: Number(card.dataset.taskVersion),
                }),
            });

            const payload = await response.json().catch(() => null);

            if (!response.ok || !payload?.task) {
                const validationMessage = Object.values(payload?.errors ?? {})
                    .flat()
                    .find((value) => typeof value === 'string');

                if (response.status === 422) {
                    failureMessage =
                        validationMessage ?? 'Check the task and reload the board.';
                } else if ([401, 419].includes(response.status)) {
                    failureMessage = 'Your session expired. Reload and sign in again.';
                } else if ([403, 404].includes(response.status)) {
                    failureMessage =
                        'This task is unavailable or your access changed. Reload the board.';
                }

                throw new Error('Status update was not confirmed.');
            }

            saved = true;
            announce('Task saved. Refreshing board…');

            const refresh = await fetch(window.location.href, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'text/html' },
            });

            if (!refresh.ok) throw new Error('Board refresh failed.');

            const page = new DOMParser().parseFromString(
                await refresh.text(),
                'text/html',
            );
            const nextBoard = page.querySelector('[data-task-board]');

            if (!nextBoard) throw new Error('Board was unavailable.');

            const scrollLeft = board.scrollLeft;
            board.replaceWith(nextBoard);
            board = nextBoard;
            board.scrollLeft = scrollLeft;
            prepareBoard();

            const focusTarget =
                document.getElementById(`task-status-${taskId}`) ?? board;

            setBusy(false);
            focusTarget.focus({ preventScroll: true });
            announce('Task status updated.');
        } catch {
            form.querySelector('[name="status"]').value = card.dataset.taskStatus;

            announce(
                saved
                    ? 'Task saved, but the board could not refresh. Reload before making another change.'
                    : failureMessage,
            );
        } finally {
            setBusy(false);
        }
    }

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-task-status-form]');

        if (!form || !board.contains(form)) return;

        event.preventDefault();

        moveTask(
            form.closest('[data-task-card]'),
            form.querySelector('[name="status"]').value,
        );
    });

    document.addEventListener('click', (event) => {
        const handle = event.target.closest('[data-task-drag-handle]');

        if (!handle || !board.contains(handle) || busy) return;

        handle.closest('[data-task-card]')
            .querySelector('[name="status"]')
            .focus();
    });

    document.addEventListener('dragstart', (event) => {
        if (!board.contains(event.target)) return;

        const handle = event.target.closest('[data-task-drag-handle]');

        if (!handle || busy) {
            event.preventDefault();
            return;
        }

        draggedCard = handle.closest('[data-task-card]');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', draggedCard.dataset.taskId);
        event.dataTransfer.setDragImage(draggedCard, 20, 20);
    });

    document.addEventListener('dragover', (event) => {
        const column = event.target.closest('[data-board-column]');

        if (!draggedCard || busy || !column || !board.contains(column)) return;

        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        clearHighlights();
        column.classList.add('ring-2', 'ring-indigo-400');
    });

    document.addEventListener('dragleave', (event) => {
        if (!board.contains(event.relatedTarget)) clearHighlights();
    });

    document.addEventListener('drop', (event) => {
        const column = event.target.closest('[data-board-column]');

        if (!draggedCard || busy || !column || !board.contains(column)) return;

        event.preventDefault();

        const card = draggedCard;
        draggedCard = null;
        clearHighlights();

        moveTask(card, column.dataset.boardColumn);
    });

    document.addEventListener('dragend', () => {
        draggedCard = null;
        clearHighlights();
    });

    prepareBoard();
}

initTaskBoard();
