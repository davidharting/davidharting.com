<x-layout.app title="Kitchen Sink" description="Component showcase page">
    <x-slot:head>
        <meta name="robots" content="noindex, nofollow" />
    </x-slot>

    <div class="max-w-4xl mx-auto space-y-16 pb-16">
        <header class="text-center">
            <h1 class="text-4xl font-bold mb-4">Kitchen Sink</h1>
            <p class="text-lg text-base-content/70">
                A showcase of components and typography for the hobbit-core
                theme
            </p>
        </header>

        {{-- Buttons Section --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Buttons
            </h2>

            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Button Variants</h3>
                <div class="flex flex-wrap gap-2">
                    <button class="btn">Default</button>
                    <button class="btn btn-primary">Primary</button>
                    <button class="btn btn-secondary">Secondary</button>
                    <button class="btn btn-accent">Accent</button>
                    <button class="btn btn-neutral">Neutral</button>
                    <button class="btn btn-ghost">Ghost</button>
                    <button class="btn btn-link">Link</button>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Button States</h3>
                <div class="flex flex-wrap gap-2">
                    <button class="btn btn-primary">Normal</button>
                    <button class="btn btn-primary btn-active">Active</button>
                    <button class="btn btn-primary" disabled>Disabled</button>
                    <button class="btn btn-primary btn-outline">Outline</button>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Button Sizes</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <button class="btn btn-xs">Tiny</button>
                    <button class="btn btn-sm">Small</button>
                    <button class="btn btn-md">Normal</button>
                    <button class="btn btn-lg">Large</button>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Status Buttons</h3>
                <div class="flex flex-wrap gap-2">
                    <button class="btn btn-info">Info</button>
                    <button class="btn btn-success">Success</button>
                    <button class="btn btn-warning">Warning</button>
                    <button class="btn btn-error">Error</button>
                </div>
            </div>
        </section>

        {{-- Cards Section --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Cards
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h3 class="card-title">Basic Card</h3>
                        <p>
                            A simple card with just text content. Cards are
                            great for grouping related information together.
                        </p>
                        <div class="card-actions justify-end">
                            <button class="btn btn-primary">Action</button>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-200 shadow-xl">
                    <div class="card-body">
                        <h3 class="card-title">
                            Featured
                            <div class="badge badge-secondary">NEW</div>
                        </h3>
                        <p>
                            This card has a badge in the title to highlight
                            featured content.
                        </p>
                        <div class="card-actions justify-end">
                            <div class="badge badge-outline">Tag 1</div>
                            <div class="badge badge-outline">Tag 2</div>
                        </div>
                    </div>
                </div>

                <div class="card bg-primary text-primary-content shadow-xl">
                    <div class="card-body">
                        <h3 class="card-title">Primary Card</h3>
                        <p>
                            A card styled with the primary color scheme for
                            emphasis.
                        </p>
                        <div class="card-actions justify-end">
                            <button class="btn">Buy Now</button>
                        </div>
                    </div>
                </div>

                <div class="card bg-neutral text-neutral-content shadow-xl">
                    <div class="card-body">
                        <h3 class="card-title">Neutral Card</h3>
                        <p>
                            A card with neutral colors for a more subdued
                            appearance.
                        </p>
                        <div class="card-actions justify-end">
                            <button class="btn btn-ghost">Learn More</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Form Section --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Complex Form
            </h2>

            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="card-title">Registration Form</h3>

                    <form class="space-y-6">
                        {{-- Text Inputs --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-control w-full">
                                <label class="label">
                                    <span class="label-text">First Name</span>
                                    <span class="label-text-alt text-error">
                                        Required
                                    </span>
                                </label>
                                <input
                                    type="text"
                                    placeholder="Bilbo"
                                    class="input input-bordered w-full"
                                />
                            </div>

                            <div class="form-control w-full">
                                <label class="label">
                                    <span class="label-text">Last Name</span>
                                </label>
                                <input
                                    type="text"
                                    placeholder="Baggins"
                                    class="input input-bordered w-full"
                                />
                            </div>
                        </div>

                        <div class="form-control w-full">
                            <label class="label">
                                <span class="label-text">Email Address</span>
                            </label>
                            <input
                                type="email"
                                placeholder="bilbo@bagend.shire"
                                class="input input-bordered w-full"
                            />
                            <label class="label">
                                <span
                                    class="label-text-alt text-base-content/60"
                                >
                                    We'll never share your email with anyone
                                    else.
                                </span>
                            </label>
                        </div>

                        {{-- Select --}}
                        <div class="form-control w-full">
                            <label class="label">
                                <span class="label-text">Favorite Meal</span>
                            </label>
                            <select class="select select-bordered w-full">
                                <option disabled selected>Pick one</option>
                                <option>First Breakfast</option>
                                <option>Second Breakfast</option>
                                <option>Elevenses</option>
                                <option>Luncheon</option>
                                <option>Afternoon Tea</option>
                                <option>Dinner</option>
                                <option>Supper</option>
                            </select>
                        </div>

                        {{-- Textarea --}}
                        <div class="form-control w-full">
                            <label class="label">
                                <span class="label-text">Bio</span>
                            </label>
                            <textarea
                                class="textarea textarea-bordered h-24 w-full"
                                placeholder="Tell us about yourself..."
                            ></textarea>
                        </div>

                        {{-- Range --}}
                        <div class="form-control w-full">
                            <label class="label">
                                <span class="label-text">
                                    Adventure Enthusiasm
                                </span>
                                <span class="label-text-alt">0 - 100</span>
                            </label>
                            <input
                                type="range"
                                min="0"
                                max="100"
                                value="40"
                                class="range range-primary"
                            />
                            <div
                                class="flex w-full justify-between px-2 text-xs"
                            >
                                <span>Homebody</span>
                                <span>|</span>
                                <span>|</span>
                                <span>|</span>
                                <span>Adventurer</span>
                            </div>
                        </div>

                        {{-- Checkboxes and Radios --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <span class="label-text font-semibold">
                                    Interests
                                </span>
                                <div class="form-control">
                                    <label
                                        class="label cursor-pointer justify-start gap-4"
                                    >
                                        <input
                                            type="checkbox"
                                            checked
                                            class="checkbox checkbox-primary"
                                        />
                                        <span class="label-text">
                                            Gardening
                                        </span>
                                    </label>
                                </div>
                                <div class="form-control">
                                    <label
                                        class="label cursor-pointer justify-start gap-4"
                                    >
                                        <input
                                            type="checkbox"
                                            class="checkbox checkbox-primary"
                                        />
                                        <span class="label-text">Poetry</span>
                                    </label>
                                </div>
                                <div class="form-control">
                                    <label
                                        class="label cursor-pointer justify-start gap-4"
                                    >
                                        <input
                                            type="checkbox"
                                            class="checkbox checkbox-primary"
                                        />
                                        <span class="label-text">
                                            Mapmaking
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <span class="label-text font-semibold">
                                    Preferred Dwelling
                                </span>
                                <div class="form-control">
                                    <label
                                        class="label cursor-pointer justify-start gap-4"
                                    >
                                        <input
                                            type="radio"
                                            name="dwelling"
                                            class="radio radio-secondary"
                                            checked
                                        />
                                        <span class="label-text">
                                            Hobbit Hole
                                        </span>
                                    </label>
                                </div>
                                <div class="form-control">
                                    <label
                                        class="label cursor-pointer justify-start gap-4"
                                    >
                                        <input
                                            type="radio"
                                            name="dwelling"
                                            class="radio radio-secondary"
                                        />
                                        <span class="label-text">Cottage</span>
                                    </label>
                                </div>
                                <div class="form-control">
                                    <label
                                        class="label cursor-pointer justify-start gap-4"
                                    >
                                        <input
                                            type="radio"
                                            name="dwelling"
                                            class="radio radio-secondary"
                                        />
                                        <span class="label-text">Tower</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Toggle --}}
                        <div class="form-control">
                            <label
                                class="label cursor-pointer justify-start gap-4"
                            >
                                <input
                                    type="checkbox"
                                    class="toggle toggle-accent"
                                    checked
                                />
                                <span class="label-text">
                                    Receive second breakfast notifications
                                </span>
                            </label>
                        </div>

                        {{-- File Input --}}
                        <div class="form-control w-full">
                            <label class="label">
                                <span class="label-text">Upload Avatar</span>
                            </label>
                            <input
                                type="file"
                                class="file-input file-input-bordered w-full"
                            />
                        </div>

                        {{-- Input Variants --}}
                        <div class="space-y-4">
                            <h4 class="font-semibold">Input States</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <input
                                    type="text"
                                    placeholder="Primary"
                                    class="input input-bordered input-primary w-full"
                                />
                                <input
                                    type="text"
                                    placeholder="Secondary"
                                    class="input input-bordered input-secondary w-full"
                                />
                                <input
                                    type="text"
                                    placeholder="Success"
                                    class="input input-bordered input-success w-full"
                                />
                                <input
                                    type="text"
                                    placeholder="Error"
                                    class="input input-bordered input-error w-full"
                                />
                            </div>
                        </div>

                        {{-- Form Actions --}}
                        <div class="flex justify-end gap-4 pt-4">
                            <button type="button" class="btn btn-ghost">
                                Cancel
                            </button>
                            <button type="button" class="btn btn-primary">
                                Submit Registration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        {{-- Alerts Section --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Alerts
            </h2>

            <div class="space-y-4">
                <div role="alert" class="alert">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        class="stroke-info shrink-0 w-6 h-6"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        ></path>
                    </svg>
                    <span>A neutral alert for general information.</span>
                </div>

                <div role="alert" class="alert alert-info">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        class="stroke-current shrink-0 w-6 h-6"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        ></path>
                    </svg>
                    <span>Info: New features are available!</span>
                </div>

                <div role="alert" class="alert alert-success">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="stroke-current shrink-0 h-6 w-6"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <span>Success: Your changes have been saved!</span>
                </div>

                <div role="alert" class="alert alert-warning">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="stroke-current shrink-0 h-6 w-6"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                        />
                    </svg>
                    <span>Warning: Please check your input!</span>
                </div>

                <div role="alert" class="alert alert-error">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="stroke-current shrink-0 h-6 w-6"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <span>Error: Something went wrong!</span>
                </div>
            </div>
        </section>

        {{-- Badges Section --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Badges
            </h2>

            <div class="flex flex-wrap gap-2">
                <div class="badge">default</div>
                <div class="badge badge-primary">primary</div>
                <div class="badge badge-secondary">secondary</div>
                <div class="badge badge-accent">accent</div>
                <div class="badge badge-neutral">neutral</div>
                <div class="badge badge-ghost">ghost</div>
            </div>

            <div class="flex flex-wrap gap-2">
                <div class="badge badge-outline">outline</div>
                <div class="badge badge-primary badge-outline">primary</div>
                <div class="badge badge-secondary badge-outline">secondary</div>
                <div class="badge badge-accent badge-outline">accent</div>
            </div>

            <div class="flex flex-wrap gap-2">
                <div class="badge badge-info gap-2">info</div>
                <div class="badge badge-success gap-2">success</div>
                <div class="badge badge-warning gap-2">warning</div>
                <div class="badge badge-error gap-2">error</div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="badge badge-lg">large</div>
                <div class="badge badge-md">medium</div>
                <div class="badge badge-sm">small</div>
                <div class="badge badge-xs">tiny</div>
            </div>
        </section>

        {{-- Progress and Loading --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Progress & Loading
            </h2>

            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Progress Bars</h3>
                <progress
                    class="progress w-full"
                    value="0"
                    max="100"
                ></progress>
                <progress
                    class="progress progress-primary w-full"
                    value="25"
                    max="100"
                ></progress>
                <progress
                    class="progress progress-secondary w-full"
                    value="50"
                    max="100"
                ></progress>
                <progress
                    class="progress progress-accent w-full"
                    value="75"
                    max="100"
                ></progress>
                <progress
                    class="progress progress-success w-full"
                    value="100"
                    max="100"
                ></progress>
            </div>

            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Loading Spinners</h3>
                <div class="flex flex-wrap gap-4">
                    <span class="loading loading-spinner loading-xs"></span>
                    <span class="loading loading-spinner loading-sm"></span>
                    <span class="loading loading-spinner loading-md"></span>
                    <span class="loading loading-spinner loading-lg"></span>
                </div>
                <div class="flex flex-wrap gap-4">
                    <span class="loading loading-dots loading-xs"></span>
                    <span class="loading loading-dots loading-sm"></span>
                    <span class="loading loading-dots loading-md"></span>
                    <span class="loading loading-dots loading-lg"></span>
                </div>
            </div>
        </section>

        {{-- Tabs --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Tabs
            </h2>

            <div role="tablist" class="tabs tabs-bordered">
                <a role="tab" class="tab">Tab 1</a>
                <a role="tab" class="tab tab-active">Tab 2</a>
                <a role="tab" class="tab">Tab 3</a>
            </div>

            <div role="tablist" class="tabs tabs-lifted">
                <a role="tab" class="tab">Tab 1</a>
                <a role="tab" class="tab tab-active">Tab 2</a>
                <a role="tab" class="tab">Tab 3</a>
            </div>

            <div role="tablist" class="tabs tabs-boxed">
                <a role="tab" class="tab">Tab 1</a>
                <a role="tab" class="tab tab-active">Tab 2</a>
                <a role="tab" class="tab">Tab 3</a>
            </div>
        </section>

        {{-- Collapse/Accordion --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Accordion
            </h2>

            <div class="space-y-2">
                <div class="collapse collapse-arrow bg-base-200">
                    <input type="radio" name="accordion" checked />
                    <div class="collapse-title font-medium">
                        What is second breakfast?
                    </div>
                    <div class="collapse-content">
                        <p>
                            Second breakfast is the meal that hobbits eat after
                            first breakfast but before elevenses. It typically
                            occurs around 9am and is an essential part of the
                            hobbit diet.
                        </p>
                    </div>
                </div>
                <div class="collapse collapse-arrow bg-base-200">
                    <input type="radio" name="accordion" />
                    <div class="collapse-title font-medium">
                        How many meals do hobbits eat?
                    </div>
                    <div class="collapse-content">
                        <p>
                            Hobbits traditionally eat seven meals a day:
                            breakfast, second breakfast, elevenses, luncheon,
                            afternoon tea, dinner, and supper.
                        </p>
                    </div>
                </div>
                <div class="collapse collapse-arrow bg-base-200">
                    <input type="radio" name="accordion" />
                    <div class="collapse-title font-medium">
                        Why do hobbits have round doors?
                    </div>
                    <div class="collapse-content">
                        <p>
                            Hobbit holes have round doors because they are built
                            into hillsides and the circular shape provides
                            structural integrity while also reflecting the
                            hobbits' preference for comfort and coziness over
                            harsh angles.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Stats --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Stats
            </h2>

            <div class="stats shadow w-full">
                <div class="stat">
                    <div class="stat-figure text-primary">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            class="inline-block w-8 h-8 stroke-current"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
                            ></path>
                        </svg>
                    </div>
                    <div class="stat-title">Total Likes</div>
                    <div class="stat-value text-primary">25.6K</div>
                    <div class="stat-desc">21% more than last month</div>
                </div>

                <div class="stat">
                    <div class="stat-figure text-secondary">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            class="inline-block w-8 h-8 stroke-current"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"
                            ></path>
                        </svg>
                    </div>
                    <div class="stat-title">Page Views</div>
                    <div class="stat-value text-secondary">2.6M</div>
                    <div class="stat-desc">14% more than last month</div>
                </div>

                <div class="stat">
                    <div class="stat-figure text-accent">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            class="inline-block w-8 h-8 stroke-current"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"
                            ></path>
                        </svg>
                    </div>
                    <div class="stat-title">Tasks Done</div>
                    <div class="stat-value text-accent">86%</div>
                    <div class="stat-desc">31 tasks remaining</div>
                </div>
            </div>
        </section>

        {{-- Table --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Table
            </h2>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Favorite Color</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th>1</th>
                            <td>Frodo Baggins</td>
                            <td>Ring-bearer</td>
                            <td>
                                <div class="badge badge-primary">Green</div>
                            </td>
                        </tr>
                        <tr class="hover">
                            <th>2</th>
                            <td>Samwise Gamgee</td>
                            <td>Gardener</td>
                            <td>
                                <div class="badge badge-secondary">Brown</div>
                            </td>
                        </tr>
                        <tr>
                            <th>3</th>
                            <td>Gandalf</td>
                            <td>Wizard</td>
                            <td>
                                <div class="badge badge-accent">
                                    Grey (then White)
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Timeline --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Timeline
            </h2>

            <ul class="timeline timeline-vertical">
                <li>
                    <div class="timeline-start timeline-box">
                        First Breakfast
                    </div>
                    <div class="timeline-middle">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="w-5 h-5 text-primary"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                    <hr class="bg-primary" />
                </li>
                <li>
                    <hr class="bg-primary" />
                    <div class="timeline-middle">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="w-5 h-5 text-primary"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                    <div class="timeline-end timeline-box">
                        Second Breakfast
                    </div>
                    <hr class="bg-primary" />
                </li>
                <li>
                    <hr class="bg-primary" />
                    <div class="timeline-start timeline-box">Elevenses</div>
                    <div class="timeline-middle">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="w-5 h-5"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                    <hr />
                </li>
                <li>
                    <hr />
                    <div class="timeline-middle">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="w-5 h-5"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                    <div class="timeline-end timeline-box">Luncheon</div>
                </li>
            </ul>
        </section>

        {{-- Tooltip --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Tooltips
            </h2>

            <div class="flex flex-wrap gap-4">
                <div class="tooltip" data-tip="Default tooltip">
                    <button class="btn">Hover me</button>
                </div>
                <div class="tooltip tooltip-primary" data-tip="Primary tooltip">
                    <button class="btn btn-primary">Primary</button>
                </div>
                <div
                    class="tooltip tooltip-secondary"
                    data-tip="Secondary tooltip"
                >
                    <button class="btn btn-secondary">Secondary</button>
                </div>
                <div class="tooltip tooltip-accent" data-tip="Accent tooltip">
                    <button class="btn btn-accent">Accent</button>
                </div>
            </div>
        </section>

        {{-- Typography Section --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Typography (Tailwind Prose)
            </h2>

            <article class="prose prose-lg max-w-none">
                <h1>Concerning Hobbits</h1>
                <p class="lead">
                    This book is largely concerned with Hobbits, and from its
                    pages a reader may discover much of their character and a
                    little of their history.
                </p>

                <h2>A Long-Expected Party</h2>
                <p>
                    When Mr. Bilbo Baggins of Bag End announced that he would
                    shortly be celebrating his
                    <strong>eleventy-first birthday</strong>
                    with a party of special magnificence, there was much talk
                    and excitement in Hobbiton.
                </p>

                <p>
                    Bilbo was very rich and very peculiar, and had been the
                    wonder of the Shire for sixty years, ever since his
                    remarkable disappearance and unexpected return. The riches
                    he had brought back from his travels had now become a local
                    legend, and it was popularly believed, whatever the old folk
                    might say, that
                    <em>
                        the Hill at Bag End was full of tunnels stuffed with
                        treasure
                    </em>
                    .
                </p>

                <blockquote>
                    <p>
                        "It's a dangerous business, Frodo, going out your door.
                        You step onto the road, and if you don't keep your feet,
                        there's no knowing where you might be swept off to."
                    </p>
                </blockquote>

                <h3>The Seven Hobbit Meals</h3>
                <p>
                    Hobbits are known for their love of food, and they
                    traditionally observe seven meals throughout the day:
                </p>

                <ol>
                    <li>
                        <strong>Breakfast</strong>
                        — The first meal of the day
                    </li>
                    <li>
                        <strong>Second Breakfast</strong>
                        — A beloved hobbit tradition
                    </li>
                    <li>
                        <strong>Elevenses</strong>
                        — A light mid-morning snack
                    </li>
                    <li>
                        <strong>Luncheon</strong>
                        — The midday meal
                    </li>
                    <li>
                        <strong>Afternoon Tea</strong>
                        — Tea with small treats
                    </li>
                    <li>
                        <strong>Dinner</strong>
                        — The main evening meal
                    </li>
                    <li>
                        <strong>Supper</strong>
                        — A light meal before bed
                    </li>
                </ol>

                <h3>Characteristics of Hobbits</h3>
                <ul>
                    <li>They are a little people, smaller than dwarves</li>
                    <li>They have no beards</li>
                    <li>
                        Their feet have tough, leathery soles and thick warm
                        brown hair
                    </li>
                    <li>They rarely wear shoes</li>
                    <li>They are inclined to be fat in the stomach</li>
                </ul>

                <h4>A Note on Hobbit Architecture</h4>
                <p>
                    The finest hobbit-holes have
                    <mark>round doors</mark>
                    , with a perfectly round hole for a window. The door opens
                    on to a tube-shaped hall like a tunnel: a very comfortable
                    tunnel without smoke, with paneled walls, and floors tiled
                    and carpeted.
                </p>

                <pre><code>// Example of hobbit hospitality
function offerMeal(guest) {
    const meals = ['breakfast', 'second breakfast', 'elevenses'];
    meals.forEach(meal => {
        serve(guest, meal);
        if (guest.isStillHungry) {
            offerSeconds(guest, meal);
        }
    });
}</code></pre>

                <p>
                    Here is a
                    <a href="#">link to more information</a>
                    about the history of hobbits and their customs.
                </p>

                <hr />

                <p>
                    Finally, remember this wisdom:
                    <kbd>Ctrl</kbd>
                    +
                    <kbd>Z</kbd>
                    cannot undo an adventure once begun, so choose your path
                    wisely!
                </p>
            </article>
        </section>

        {{-- Divider --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Dividers
            </h2>

            <div class="flex flex-col w-full">
                <div
                    class="grid h-20 card bg-base-300 rounded-box place-items-center"
                >
                    Content Above
                </div>
                <div class="divider">OR</div>
                <div
                    class="grid h-20 card bg-base-300 rounded-box place-items-center"
                >
                    Content Below
                </div>
            </div>

            <div class="flex w-full">
                <div
                    class="grid h-20 flex-grow card bg-base-300 rounded-box place-items-center"
                >
                    Left
                </div>
                <div class="divider divider-horizontal">AND</div>
                <div
                    class="grid h-20 flex-grow card bg-base-300 rounded-box place-items-center"
                >
                    Right
                </div>
            </div>
        </section>

        {{-- Theme Toggle Demo --}}
        <section class="space-y-6">
            <h2 class="text-2xl font-bold border-b border-base-300 pb-2">
                Color Palette
            </h2>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div
                    class="bg-primary text-primary-content p-4 rounded-box text-center"
                >
                    Primary
                </div>
                <div
                    class="bg-secondary text-secondary-content p-4 rounded-box text-center"
                >
                    Secondary
                </div>
                <div
                    class="bg-accent text-accent-content p-4 rounded-box text-center"
                >
                    Accent
                </div>
                <div
                    class="bg-neutral text-neutral-content p-4 rounded-box text-center"
                >
                    Neutral
                </div>
                <div
                    class="bg-base-100 text-base-content p-4 rounded-box text-center border"
                >
                    Base 100
                </div>
                <div
                    class="bg-base-200 text-base-content p-4 rounded-box text-center"
                >
                    Base 200
                </div>
                <div
                    class="bg-base-300 text-base-content p-4 rounded-box text-center"
                >
                    Base 300
                </div>
                <div
                    class="bg-base-content text-base-100 p-4 rounded-box text-center"
                >
                    Base Content
                </div>
                <div
                    class="bg-info text-info-content p-4 rounded-box text-center"
                >
                    Info
                </div>
                <div
                    class="bg-success text-success-content p-4 rounded-box text-center"
                >
                    Success
                </div>
                <div
                    class="bg-warning text-warning-content p-4 rounded-box text-center"
                >
                    Warning
                </div>
                <div
                    class="bg-error text-error-content p-4 rounded-box text-center"
                >
                    Error
                </div>
            </div>
        </section>
    </div>
</x-layout.app>
