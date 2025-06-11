<x-layout.app>
    <div class="m-auto mx-4 sm:mx-auto max-w-2xl my-16">
        <div
            class="flex flex-col sm:flex-row w-full sm:space-x-4"
        >
            <flux:avatar src="headshot.jpg" size='xl' />
            <div>
                <flux:heading class="font-serif" size='xl' level='1'>
                    Hi! I am David Harting,
                </flux:heading>
                <flux:text class="">and it's a great day to build software ☀️</flux:text>
            </div>
        </div>

        <div class="mt-9">
            <div class="prose prose-sm prose-pink dark:prose-invert">
                    <p>
                        I am an experienced, full-stack software engineer from
                        Westfield, Indiana. My focus in my career has been web
                        apps that enable people to work with data. For the past
                        4 years, I've worked on a Cloud IDE at dbt Labs.
                    </p>
                    <p>
                        At work, I am happiest working closely with product and
                        design to navigate tradeoffs and to ship quickly. I am
                        passionate about code review and testing.
                    </p>
                    <p>
                        I believe in working hard and living slow. I share life
                        with my wife Katie, my son AJ, and our dog Andi. I am
                        fortunate enough to enjoy leisure time, which is filled
                        with walks, wine, books, and games.
                    </p>
                    <p
                        x-data="{
                            email: 'connect@davidharting.com',
                            showFeedback: false,
                            tooltipText: 'Copy 📋',
                            copy() {
                                navigator.clipboard.writeText(this.email)

                                this.showFeedback = true
                                setTimeout(() => (this.showFeedback = false), 1500)
                            },
                        }"
                    >
                        You can email me at
                        <span
                            class="tooltip tooltip-primary"
                            x-bind:class="showFeedback && 'tooltip-open'"
                            x-bind:data-tip="showFeedback ? 'Copied ✅' : 'Copy 📋'"
                        >
                            <span
                                class="link link-primary"
                                x-on:click="copy()"
                            >
                                connect@davidharting.com
                            </span>
                        </span>

                        Or, find me on
                        <a
                            href="https://github.com/davidharting"
                            class="link link-primary"
                        >
                            GitHub
                        </a>
                        ,
                        <a
                            href="https://bsky.app/profile/davidharting.com"
                            class="link link-primary"
                        >
                            Bluesky
                        </a>
                        , and
                        <a
                            href="https://www.linkedin.com/in/davidharting/"
                            class="link link-primary"
                        >
                            LinkedIn
                        </a>
                        .
                    </p>
                    <p>While you're here, why not click the button?</p>
                </div>
            </div>
        </div>
        <div class="mt-12">
            <livewire:upclick lazy />
        </div>
    </div>
</x-layout.app>
