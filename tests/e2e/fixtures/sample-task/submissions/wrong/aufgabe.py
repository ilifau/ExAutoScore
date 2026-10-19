def summe(zahlen):
    return sum(zahlen)


def ist_primzahl(n):
    return all(n % t for t in range(2, n))


def fakultaet(n):
    ergebnis = 0 if n == 0 else 1
    for i in range(2, n + 1):
        ergebnis *= i
    return ergebnis
