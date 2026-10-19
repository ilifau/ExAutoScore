def summe(zahlen):
    return sum(zahlen)


def ist_primzahl(n):
    if n < 2:
        return False
    return all(n % t for t in range(2, int(n ** 0.5) + 1))


def fakultaet(n):
    ergebnis = 1
    for i in range(2, n + 1):
        ergebnis *= i
    return ergebnis
