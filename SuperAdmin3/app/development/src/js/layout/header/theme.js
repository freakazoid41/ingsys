import images from "../../../img/theme/*.jpg";

export const theme = () => {
    const WRAPPER = document.getElementById("top-themes");
    const BODY = document.body;
    let activeThemeId = localStorage.getItem("sa-theme") || 1;

    if(WRAPPER) {
        const DATA = [
            {
                id: 1,
                name: "Solaris",
            },
            {
                id: 2,
                name: "Galaxy",
            },
            {
                id: 3,
                name: "Forest",
            },
            {
                id: 4,
                name: "Red Velvet",
                new: true
            },
            {
                id: 5,
                name: "Desert",
            },
            {
                id: 6,
                name: "Northern Light",
            },
            {
                id: 7,
                name: "Sunset",
            },
            {
                id: 8,
                name: "Kinetic",
            },
        ];

        DATA.map((theme, index) => {
            // Create theme preview thumbnails
            let item = document.createElement("button");
            item.type = "button";
            item.setAttribute("class", "d-block overflow-hidden position-relative");
            item.setAttribute("data-sa-theme-id", theme.id);
            item.innerHTML = `<img src="${images[theme.id]}" class="object-fit-cover rounded h-20 w-100" alt="${theme.name}" />
                                <span class="position-absolute bottom-0 end-0 lh-2 py-2 px-4">${theme.name}</span>`;
            
            // Add active class to the current theme preview
            if(activeThemeId == theme.id) {
                item.classList.add("active");
            }

            // Add click events
            item.onclick = () => {
                BODY.setAttribute("data-sa-theme", theme.id);
                document.querySelectorAll("#top-themes button").forEach(btn => btn.classList.remove("active"));
                item.classList.add("active");
                
                localStorage.setItem("sa-theme", theme.id);
            }

            WRAPPER.appendChild(item);
        });
    }
}