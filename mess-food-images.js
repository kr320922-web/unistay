
/*
  UniStay Mess - Automatic Food Photo Slideshow

  Example:
  Aloo Paratha, Curd, Tea

  Photos:
  Paratha -> Curd -> Tea -> Repeat

  Slide changes every 3.5 seconds.
*/

(() => {
  "use strict";

  const INTERVAL_MS = 3500;

  const API = "https://en.wikipedia.org/w/api.php";

  const photoCache = new Map();

  /* Food name matching */

  const knownFoods = [
    [/\b(aloo|alu)\s+para(?:tha|ntha)\b/i, "Aloo paratha"],
    [/\bpara(?:tha|ntha)\b/i, "Paratha"],
    [/\bome(?:lette|let|lete)\b/i, "Omelette"],
    [/\bsamosa\b/i, "Samosa"],
    [/\b(pakora|pakoda|pakode)\b/i, "Pakora"],
    [/\bbiryani\b/i, "Biryani"],
    [/\bpaneer tikka\b/i, "Paneer tikka"],
    [/\bshahi paneer\b/i, "Shahi paneer"],
    [/\bpalak paneer\b/i, "Palak paneer"],
    [/\bpaneer\b/i, "Paneer"],
    [/\bdal makhani\b/i, "Dal makhani"],
    [/\b(dal|daal)\b/i, "Dal"],
    [/\brajma\b/i, "Rajma"],
    [/\b(chole|chana masala)\b/i, "Chana masala"],
    [/\bidli\b/i, "Idli"],
    [/\bdosa\b/i, "Dosa"],
    [/\bpoha\b/i, "Poha"],
    [/\bupma\b/i, "Upma"],
    [/\b(puri|poori)\b/i, "Puri (food)"],
    [/\b(roti|chapati)\b/i, "Roti"],
    [/\bfried rice\b/i, "Fried rice"],
    [/\b(rice|chawal)\b/i, "Rice"],
    [/\b(noodles|chowmein)\b/i, "Chow mein"],
    [/\bpizza\b/i, "Pizza"],
    [/\bburger\b/i, "Hamburger"],
    [/\bsandwich\b/i, "Sandwich"],
    [/\b(tea|chai)\b/i, "Masala chai"],
    [/\bcoffee\b/i, "Coffee"],
    [/\b(curd|dahi|yogurt)\b/i, "Dahi (curd)"],
    [/\bsalad\b/i, "Salad"],
    [/\bmilk\b/i, "Milk"],
    [/\bkheer\b/i, "Kheer"],
    [/\bgulab jamun\b/i, "Gulab jamun"],
    [/\bjalebi\b/i, "Jalebi"],
    [/\bhalwa\b/i, "Halva"]
  ];

  /* Split food items */

  function splitFoods(text) {
    return [
      ...new Set(
        String(text || "")
          .replace(
            /^\s*(breakfast|lunch|snacks|dinner)\s*:\s*/i,
            ""
          )
          .split(/[,;\n|]+/)
          .map(item =>
            item.trim()
              .replace(/\s+/g, " ")
              .slice(0, 70)
          )
          .filter(Boolean)
      )
    ].slice(0, 8);
  }

  /* Get suitable search name */

  function getSearchName(food) {
    const match = knownFoods.find(
      ([pattern]) => pattern.test(food)
    );

    return match ? match[1] : food;
  }

  function normalize(text) {
    return String(text)
      .toLowerCase()
      .replace(/\s*\([^)]*\)/g, "")
      .trim();
  }

  /* Fetch food image */

  function getFoodPhoto(food) {
    const name = getSearchName(food);

    if (photoCache.has(name)) {
      return photoCache.get(name);
    }

    const request = (async () => {
      const params = new URLSearchParams({
        action: "query",
        generator: "search",
        gsrsearch: name,
        gsrnamespace: "0",
        gsrlimit: "5",
        prop: "pageimages|info",
        piprop: "thumbnail",
        pithumbsize: "750",
        inprop: "url",
        format: "json",
        origin: "*"
      });

      const controller = new AbortController();

      const timeout = setTimeout(
        () => controller.abort(),
        7000
      );

      try {
        const response = await fetch(
          API + "?" + params,
          {
            signal: controller.signal,
            credentials: "omit"
          }
        );

        if (!response.ok) {
          return null;
        }

        const data = await response.json();

        const pages = Object.values(
          data.query?.pages || {}
        ).filter(page =>
          page.thumbnail?.source && page.fullurl
        );

        const target = normalize(name);

        const selected =
          pages.find(page =>
            normalize(page.title) === target
          ) ||
          pages.find(page =>
            normalize(page.title).startsWith(target)
          ) ||
          pages.find(page =>
            normalize(page.title).includes(target) &&
            !/^list of /i.test(page.title)
          );

        return selected
          ? {
              src: selected.thumbnail.source,
              url: selected.fullurl
            }
          : null;

      } catch (error) {
        return null;

      } finally {
        clearTimeout(timeout);
      }
    })();

    photoCache.set(name, request);

    return request;
  }

  /* Slideshow CSS */

  const style = document.createElement("style");

  style.id = "unistay-food-slideshow-style";

  style.textContent = `

    .unistay-food-slider {
      position: relative;
      z-index: 1;
      width: 100%;
      height: 100%;
      overflow: hidden;
      border-radius: 12px;
      background: #e9f0f2;
      isolation: isolate;
    }

    .unistay-food-slide {
      position: absolute;
      inset: 0;
      opacity: 0;
      visibility: hidden;
      transition:
        opacity 0.55s ease,
        visibility 0.55s;
    }

    .unistay-food-slide.is-active {
      opacity: 1;
      visibility: visible;
      z-index: 1;
    }

    .unistay-food-slide img {
      display: block !important;
      width: 100% !important;
      height: 100% !important;
      object-fit: cover !important;
      border-radius: 0 !important;
    }

    /* Food name on photo */

    .unistay-food-caption {
      position: absolute;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: 2;

      padding: 25px 44px 15px;

      color: white;

      font-family:
        "Segoe UI", Arial, sans-serif;

      font-size: 16px;
      font-weight: 700;
      line-height: 1.35;
      text-align: center;

      text-shadow: 0 1px 4px #0008;

      background: linear-gradient(
        transparent,
        rgba(12, 30, 42, 0.87)
      );
    }

    /* Previous / Next arrows */

    .unistay-food-arrow {
      position: absolute;
      z-index: 4;
      top: 50%;

      transform: translateY(-50%);

      display: grid;
      place-items: center;

      width: 32px;
      height: 32px;

      border: 0;
      border-radius: 50%;

      background: rgba(255,255,255,0.9);
      color: #17384a;

      font-size: 24px;
      cursor: pointer;
    }

    .unistay-food-arrow:hover {
      background: white;
    }

    .unistay-food-prev {
      left: 9px;
    }

    .unistay-food-next {
      right: 9px;
    }

    /* Bottom navigation dots */

    .unistay-food-dots {
      position: absolute;
      z-index: 4;
      bottom: 5px;
      left: 50%;

      transform: translateX(-50%);

      display: flex;
      gap: 5px;
    }

    .unistay-food-dot {
      width: 6px;
      height: 6px;

      padding: 0;
      border: 0;
      border-radius: 50%;

      background: rgba(255,255,255,0.5);
      cursor: pointer;
    }

    .unistay-food-dot.is-active {
      width: 16px;
      border-radius: 6px;
      background: white;
    }

    /* Photo source */

    .unistay-food-source {
      position: absolute;
      right: 7px;
      top: 7px;
      z-index: 3;

      padding: 3px 6px;

      border-radius: 5px;

      background: rgba(255,255,255,0.9);
      color: #264b5b;

      font-size: 9px;
      font-weight: 600;
      text-decoration: none;
    }

    /* Larger menu text */

    .meal-food {
      font-size: 15px !important;
      font-weight: 600 !important;
      line-height: 1.7 !important;
      color: #273b4b !important;
    }

    .upcoming-meal {
      font-size: 13px !important;
      line-height: 1.6 !important;
    }

    .upcoming-meal strong {
      font-size: 13px !important;
    }

    /* Photo sizes */

    .meal-photo,
    .upcoming-photo,
    .meal-image {
      overflow: hidden;
    }

    .meal-photo {
      height: 180px !important;
    }

    .upcoming-photo {
      height: 135px !important;
    }

    .meal-image {
      height: 190px !important;
    }

    .meal-image::after {
      pointer-events: none;
      z-index: 0;
    }

    @media (max-width: 520px) {
      .meal-photo {
        height: 195px !important;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .unistay-food-slide {
        transition: none;
      }
    }

  `;

  if (
    !document.getElementById(
      "unistay-food-slideshow-style"
    )
  ) {
    document.head.appendChild(style);
  }

  /* Build slideshow */

  function createSlideshow(originalImage) {
    const foods = splitFoods(
      originalImage.dataset.food
    );

    if (!foods.length) {
      return;
    }

    const holder = originalImage.parentElement;

    if (
      !holder ||
      holder.querySelector(".unistay-food-slider")
    ) {
      return;
    }

    const fallback =
      originalImage.dataset.fallback ||
      originalImage.getAttribute("src");

    const slider = document.createElement("div");

    slider.className = "unistay-food-slider";

    slider.setAttribute(
      "aria-label",
      "Food photos slideshow"
    );

    const slides = [];
    const dots = [];

    let activeIndex = 0;
    let timer = null;

    /* Create one slide per food item */

    foods.forEach((food, index) => {
      const slide = document.createElement("div");

      slide.className =
        "unistay-food-slide" +
        (index === 0 ? " is-active" : "");

      const image = document.createElement("img");

      image.alt = food;
      image.loading = "lazy";
      image.decoding = "async";
      image.src = fallback;

      image.addEventListener("error", () => {
        if (image.src !== fallback) {
          image.src = fallback;
        }
      });

      const caption = document.createElement("div");

      caption.className = "unistay-food-caption";
      caption.textContent = food;

      slide.append(image, caption);
      slider.appendChild(slide);

      slides.push(slide);

      /* Load matching photo */

      getFoodPhoto(food).then(photo => {
        if (!photo || !slider.isConnected) {
          return;
        }

        image.src = photo.src;

        const source = document.createElement("a");

        source.className = "unistay-food-source";
        source.textContent = "Source";

        source.href = photo.url;
        source.target = "_blank";
        source.rel = "noopener noreferrer";

        source.title =
          "View photo source for " + food;

        slide.appendChild(source);
      });
    });

    /* Show selected slide */

    function showSlide(index) {
      activeIndex =
        (index + slides.length) % slides.length;

      slides.forEach((slide, i) => {
        const isActive = i === activeIndex;

        slide.classList.toggle(
          "is-active",
          isActive
        );

        slide.setAttribute(
          "aria-hidden",
          isActive ? "false" : "true"
        );

        const link = slide.querySelector("a");

        if (link) {
          link.tabIndex = isActive ? 0 : -1;
        }
      });

      dots.forEach((dot, i) => {
        dot.classList.toggle(
          "is-active",
          i === activeIndex
        );
      });
    }

    /* Automatic slideshow */

    function stopSlideshow() {
      if (timer !== null) {
        clearInterval(timer);
        timer = null;
      }
    }

    function startSlideshow() {
      stopSlideshow();

      if (slides.length < 2) {
        return;
      }

      if (document.hidden) {
        return;
      }

      if (
        window.matchMedia(
          "(prefers-reduced-motion: reduce)"
        ).matches
      ) {
        return;
      }

      timer = setInterval(() => {
        showSlide(activeIndex + 1);
      }, INTERVAL_MS);
    }

    /* Arrows and dots */

    if (slides.length > 1) {
      const previous = document.createElement(
        "button"
      );

      previous.type = "button";

      previous.className =
        "unistay-food-arrow unistay-food-prev";

      previous.textContent = "‹";

      previous.setAttribute(
        "aria-label",
        "Previous food"
      );

      previous.addEventListener("click", () => {
        showSlide(activeIndex - 1);
        startSlideshow();
      });

      const next = document.createElement(
        "button"
      );

      next.type = "button";

      next.className =
        "unistay-food-arrow unistay-food-next";

      next.textContent = "›";

      next.setAttribute(
        "aria-label",
        "Next food"
      );

      next.addEventListener("click", () => {
        showSlide(activeIndex + 1);
        startSlideshow();
      });

      slider.append(previous, next);

      const navigation = document.createElement(
        "div"
      );

      navigation.className = "unistay-food-dots";

      foods.forEach((food, index) => {
        const dot = document.createElement(
          "button"
        );

        dot.type = "button";
        dot.className = "unistay-food-dot";

        dot.setAttribute(
          "aria-label",
          "Show " + food
        );

        dot.addEventListener("click", () => {
          showSlide(index);
          startSlideshow();
        });

        navigation.appendChild(dot);
        dots.push(dot);
      });

      slider.appendChild(navigation);

      /* Pause when user interacts */

      slider.addEventListener(
        "mouseenter",
        stopSlideshow
      );

      slider.addEventListener(
        "mouseleave",
        startSlideshow
      );

      slider.addEventListener(
        "focusin",
        stopSlideshow
      );

      slider.addEventListener(
        "focusout",
        event => {
          if (
            !slider.contains(event.relatedTarget)
          ) {
            startSlideshow();
          }
        }
      );

      document.addEventListener(
        "visibilitychange",
        startSlideshow
      );
    }

    /* Replace old image */

    originalImage.style.display = "none";

    holder.appendChild(slider);

    showSlide(0);
    startSlideshow();
  }

  /* Initialize all food photos */

  const foodImages = document.querySelectorAll(
    "img.auto-food-photo[data-food]"
  );

  if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver(
      entries => {
        entries.forEach(entry => {
          if (!entry.isIntersecting) {
            return;
          }

          observer.unobserve(entry.target);

          createSlideshow(entry.target);
        });
      },
      {
        rootMargin: "250px"
      }
    );

    foodImages.forEach(image => {
      observer.observe(image);
    });

  } else {
    foodImages.forEach(createSlideshow);
  }

})();
