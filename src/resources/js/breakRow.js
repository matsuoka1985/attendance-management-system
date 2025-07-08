document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("break-sections");
    if (!container) return;

    const initialBreaks = JSON.parse(container.dataset.initialBreaks || "[]");
    const isReadonly = container.dataset.isReadonly === "true";

    const makeRow = (rowIndex) => {
        const row = document.createElement("div");
        row.className =
            "border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10";
        row.innerHTML = `
            <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">
                ${rowIndex === 0 ? "休憩" : `休憩${rowIndex + 1}`}
            </div>
            <div class="w-full sm:w-[17rem] flex flex-col sm:flex-row gap-2 sm:gap-6 font-bold">
                <input type="time" name="breaks[${rowIndex}][start]"
                       class="break-start border rounded px-2 py-1 w-full sm:w-32 text-center"
                       ${isReadonly ? "readonly" : ""}>
                <span class="self-center">〜</span>
                <input type="time" name="breaks[${rowIndex}][end]"
                       class="break-end border rounded px-2 py-1 w-full sm:w-32 text-center"
                       ${isReadonly ? "readonly" : ""}>
            </div>`;
        return row;
    };

    const renumber = () => {
        [...container.children].forEach((row, index) => {
            row.querySelector(".text-gray-500").textContent =
                index === 0 ? "休憩" : `休憩${index + 1}`;
            row.querySelector(".break-start").name = `breaks[${index}][start]`;
            row.querySelector(".break-end").name = `breaks[${index}][end]`;
        });
    };

    const tidy = () => {
        for (let rowIndex = container.children.length - 2; rowIndex >= 0; rowIndex--) {
            const row = container.children[rowIndex];
            const startTimeValue = row.querySelector(".break-start").value;
            const endTimeValue = row.querySelector(".break-end").value;
            if (!startTimeValue && !endTimeValue) row.remove();
        }

        const last = container.lastElementChild;
        if (last) {
            const startTimeValue = last.querySelector(".break-start").value;
            const endTimeValue = last.querySelector(".break-end").value;
            if (startTimeValue && endTimeValue)
                container.appendChild(makeRow(container.children.length));
        }

        renumber();
    };

    initialBreaks.forEach((breakItem, i) => {
        const row = makeRow(i);
        row.querySelector(".break-start").value = breakItem.start ?? "";
        row.querySelector(".break-end").value = breakItem.end ?? "";
        container.appendChild(row);
    });

    if (container.children.length === 0) {
        container.appendChild(makeRow(0));
    }

    if (!isReadonly) {
        container.addEventListener("input", tidy);
    }
});
