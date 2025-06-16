export const shortcuts = () => {
    const WRAPPER = document.getElementById("top-shortcuts");

    if(WRAPPER) {
        let list = "";
        const DATA = [
            {
                label: "Calendar",
                icon: "calendar",
                url: "/",
            },
            {
                label: "Files",
                icon: "folder",
                url: "/",
            },
            {
                label: "Mail",
                icon: "envelope-simple",
                url: "/",
            },
            {
                label: "Reports",
                icon: "chart-pie-slice",
                url: "/",
            },
            {
                label: "Photos",
                icon: "image",
                url: "/",
            },
            {
                label: "Todos",
                icon: "check-square",
                url: "/",
            },
            {
                label: "Messages",
                icon: "chat-centered-dots",
                url: "/",
            },
            {
                label: "Invoice",
                icon: "invoice",
                url: "/",
            },
            {
                label: "Contacts",
                icon: "user-circle",
                url: "/",
            },
            {
                label: "FAQ",
                icon: "question",
                url: "/",
            },
            {
                label: "News",
                icon: "rss",
                url: "/",
            },
            {
                label: "Timeline",
                icon: "rows",
                url: "/",
            }
        ];

        DATA.map((item, index) => {
            list += `<a href="" class="d-block text-white">
                        <div class="bg-hover-inverse rounded text-center p-3">
                            <i class="ph ph-${item.icon} fs-3 h-12 w-12 d-inline-flex align-items-center justify-content-center rounded-circle bg-highlight-inverse"></i>
                            <span class="d-block lh-1 mt-2 fs-7">${item.label}</span>
                        </div>
                    </a>`;
        });

        WRAPPER.innerHTML = list;
    }
}