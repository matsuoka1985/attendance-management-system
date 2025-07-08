document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("break-sections");
    if (!container) return;

    const initialBreaks = JSON.parse(container.dataset.initialBreaks || "[]");
    const isReadonly = container.dataset.isReadonly === "true";

    const makeRow = (idx) => {
        const row = document.createElement("div");
        row.className =
            "border-b border-gray-200 py-4 px-6 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-10";
        row.innerHTML = `
            <div class="text-gray-500 font-semibold whitespace-nowrap flex items-center">
                ${idx === 0 ? "休憩" : `休憩${idx + 1}`}
            </div>
            <div class="w-full sm:w-[17rem] flex flex-col sm:flex-row gap-2 sm:gap-6 font-bold">
                <input type="time" name="breaks[${idx}][start]"
                       class="break-start border rounded px-2 py-1 w-full sm:w-32 text-center"
                       ${isReadonly ? "readonly" : ""}>
                <span class="self-center">〜</span>
                <input type="time" name="breaks[${idx}][end]"
                       class="break-end border rounded px-2 py-1 w-full sm:w-32 text-center"
                       ${isReadonly ? "readonly" : ""}>
            </div>`;
        return row;
    };

    const renumber = () => {
        [...container.children].forEach((row, i) => {
            row.querySelector(".text-gray-500").textContent =
                i === 0 ? "休憩" : `休憩${i + 1}`;
            row.querySelector(".break-start").name = `breaks[${i}][start]`;
            row.querySelector(".break-end").name = `breaks[${i}][end]`;
        });
    };

    const tidy = () => {
        for (let i = container.children.length - 2; i >= 0; i--) {
            const row = container.children[i];
            const s = row.querySelector(".break-start").value;
            const e = row.querySelector(".break-end").value;
            if (!s && !e) row.remove();
        }

        const last = container.lastElementChild;
        if (last) {
            const s = last.querySelector(".break-start").value;
            const e = last.querySelector(".break-end").value;
            if (s && e)
                container.appendChild(makeRow(container.children.length));
        }

        renumber();
    };

    initialBreaks.forEach((v, i) => {
        const row = makeRow(i);
        row.querySelector(".break-start").value = v.start ?? "";
        row.querySelector(".break-end").value = v.end ?? "";
        container.appendChild(row);
    });

    if (container.children.length === 0) {
        container.appendChild(makeRow(0));
    }

    if (!isReadonly) {
        container.addEventListener("input", tidy);
    }
});
