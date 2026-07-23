{if $public_setting['live_chat'] === 'crisp'}
    <script>
        window.$crisp = [];
        window.CRISP_WEBSITE_ID = "{$public_setting["crisp_id"]|escape:'javascript'}";
        (function () {
            d = document;
            s = d.createElement("script");
            s.src = "https://client.crisp.chat/l.js";
            s.async = 1;
            d.getElementsByTagName("head")[0].appendChild(s);
        })();
        $crisp.push(["safe", true])
        $crisp.push(["set", "user:nickname", "{$user->user_name|escape:'javascript'}"],
            ["set", "user:email", "{$user->email|escape:'javascript'}"],
            ["set", "session:data",
                [[
                    ["user_id", "{$user->id|escape:'javascript'}"],
                    ["user_class", "{$user->class|escape:'javascript'}"],
                    ["reg_email", "{$user->email|escape:'javascript'}"],
                    ["class_expire_time", "{$user->class_expire|escape:'javascript'}"],
                    ["available_traffic", "{$user->unusedTraffic()|escape:'javascript'}"],
                    ["balance", "{$user->money|escape:'javascript'}"]
                ]]
            ]);
    </script>
{/if}
{if $public_setting['live_chat'] === 'livechat'}
    <script>
        window.__lc = window.__lc ||
        {
        };
        window.__lc.license = "{$public_setting['livechat_license']|escape:'javascript'}";
        window.__lc.params = [
            {
                name: "{trans key='live_chat.user_id'}", value: '{$user->id|escape:'javascript'}'
            },
            {
                name: "{trans key='live_chat.user_class'}", value: '{$user->class|escape:'javascript'}'
            },
            {
                name: "{trans key='live_chat.registered_email'}", value: '{$user->email|escape:'javascript'}'
            },
            {
                name: "{trans key='live_chat.class_expire'}", value: '{$user->class_expire|escape:'javascript'}'
            },
            {
                name: "{trans key='live_chat.unused_traffic'}", value: '{$user->unusedTraffic()|escape:'javascript'}'
            },
            {
                name: "{trans key='live_chat.balance'}", value: '{$user->money|escape:'javascript'}'
            }
        ];

        (function (n, t, c) {
            function i(n) {
                return e._h ? e._h.apply(null, n) : e._q.push(n)
            }

            let e = {
                _q: [],
                _h: null,
                _v: "2.0",
                on: function () {
                    i(["on", c.call(arguments)])
                },
                once: function () {
                    i(["once", c.call(arguments)])
                },
                off: function () {
                    i(["off", c.call(arguments)])
                },
                get: function () {
                    if (!e._h) throw new Error("[LiveChatWidget] You can't use getters before load.");
                    return i(["get", c.call(arguments)])
                },
                call: function () {
                    i(["call", c.call(arguments)])
                },
                init: function () {
                    let n = t.createElement("script");
                    n.async = !0,
                        n.type = "text/javascript",
                        n.src = "https://cdn.livechatinc.com/tracking.js",
                        t.head.appendChild(n)
                }
            };
            !n.__lc.asyncInit && e.init(),
                n.LiveChatWidget = n.LiveChatWidget || e
        }(window, document, [].slice))
    </script>
{/if}
