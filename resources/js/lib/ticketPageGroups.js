function parseDateValue(value) {
    if (!value) {
        return new Date(0);
    }

    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime()) ? new Date(0) : date;
}

function getStudentDisplay(ticket) {
    const student = ticket?.student ?? {};
    const className = ticket?.班級 ?? student?.班級 ?? '';
    const studentName = ticket?.姓名 ?? student?.姓名 ?? '未知學生';

    return [className, studentName].filter(Boolean).join('　');
}

export function buildTicketPages(rows = []) {
    const grouped = new Map();

    for (const ticket of rows) {
        const studentKey = getStudentDisplay(ticket);

        if (!grouped.has(studentKey)) {
            grouped.set(studentKey, []);
        }

        grouped.get(studentKey).push(ticket);
    }

    const pages = [];

    for (const [studentKey, studentRows] of grouped.entries()) {
        const orderedRows = [...studentRows].sort((left, right) => {
            const leftDate = parseDateValue(left?.日期 ?? left?.date);
            const rightDate = parseDateValue(right?.日期 ?? right?.date);

            return leftDate - rightDate;
        });

        const totalPages = Math.max(1, Math.ceil(orderedRows.length / 5));

        for (let index = 0; index < orderedRows.length; index += 5) {
            const chunk = orderedRows.slice(index, index + 5);

            // Calculate total price for this page
            const totalPrice = chunk.reduce((sum, ticket) => {
                const amount = ticket?.金額 ?? ticket?.amount ?? 0;
                return sum + Number(amount);
            }, 0);

            // Get barcode from the first ticket (all tickets for same student should have same barcode)
            const barcode = chunk[0]?.barcode ?? '';

            pages.push({
                studentKey,
                studentDisplay: studentKey,
                pageNumber: Math.floor(index / 5) + 1,
                totalPages,
                items: chunk,
                totalPrice,
                barcode,
            });
        }
    }

    return pages;
}
