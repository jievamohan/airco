/**
 * De kop van elke dashboardpagina.
 *
 * Naast de titel en `noindex` staat hier de viewport. Op het dashboard is
 * knijp-zoomen uitgezet, zodat het als een app aanvoelt en niet als een
 * pagina; op de landingspagina blijft zoomen bewust wél werken, want daar
 * lezen mensen. iOS negeert `user-scalable=no` sinds iOS 10, dus dit raakt in
 * de praktijk Android. Het inzoomen bij focus in een invoerveld — de reden
 * waarom dit meestal wordt gevraagd — is een ander probleem, en dat lost de
 * ondergrens van 16px in dashboard.css op, óók op iOS.
 */
export function useDashboardHead(title: string) {
  useHead({
    title,
    meta: [
      { name: 'robots', content: 'noindex, nofollow' },
      { name: 'viewport', content: 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' },
    ],
  })
}
