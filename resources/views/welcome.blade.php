<x-layout.app>
    <div class='m-auto mx-4 sm:mx-auto max-w-2xl my-16'>



    <div class="flex flex-col sm:flex-row  justify-between w-full sm:space-x-4">
        <div class="avatar mb-4 sm:mb-0 flex justify-center">
            <div class="w-24 rounded-xl ring ring-primary ring-offset-base-100 ring-offset-2">
                <img src="headshot.jpg" />
            </div>
        </div>
        <div class="space-y-2">
            <h1 class="text-5xl font-extrabold font-serif">Hi! I am David Harting,</h1>
            <p class="text-lg">and it's a great day to build software ☀️</p>
        </div>
    </div>


    <div class='space-y-16 mt-14'>
        <div class='space-y-6'>
            <h2 class='font-serif font-semibold text-3xl mb-7'>About me</h2>
            <div class='space-y-12'>
            <p>I am an experienced, full-stack software engineer from Westfield, Indiana. My focus in my career has been web apps that enable people to work with data. I am now working as an engineering manager at dbt Labs, building a web-based IDE for analytics engineers.</p>
            <p>At work, I am happiest working closely with product and design to navigate tradeoffs and to ship quickly. I am passionate about code review and testing.</p>
            <p>I believe in working hard and living slow. I enjoy life with my wife and my dog. I am fortunate enough to enjoy leisure time, which is filled with walks, wine, books, and games.</p>
            </div>
        </div>
        <div class='mt-14'>
            <h2 class='font-serif font-semibold text-3xl mb-7'>Let's connect</h2>
            <div class='space-y-12'>
            <p
                x-data="{
                        email: 'connect@davidharting.com',
                        showFeedback: false,
                        tooltipText: 'Copy 📋',
                        copy() {
                            navigator.clipboard.writeText(this.email)
                            
                            this.showFeedback = true
                            setTimeout(() => this.showFeedback = false, 1500)
                        }
                }">
                You can email me at
                <span
                    class='tooltip tooltip-primary'
                    x-bind:class="showFeedback && 'tooltip-open'"
                    x-bind:data-tip="showFeedback ? 'Copied ✅' : 'Copy 📋'"
                >   
                    <span class='link link-primary' x-on:click="copy()">
                        connect@davidharting.com
                    </span>
                </span>
                
                You can also find me on
                    <a href='https://github.com/davidharting' class='link link-primary'>GitHub</a>,
                    <a href='https://twitter.com/davehrtng' class='link link-primary'>Twitter</a>,
                    and
                    <a href='https://www.linkedin.com/in/davidharting/' class='link link-primary'>LinkedIn</a>.
            </p>
            </div>
        </div>
    </div>
    </div>

</x-layout.app>