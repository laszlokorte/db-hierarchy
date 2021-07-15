Array.prototype.forEach.call(document.querySelectorAll('[data-reference-url]'), (el) => {
    const oldValue = el.value
    const isExplicit = el.dataset.explicit == true || !el.required
    const expandLimit = el.dataset.expandLimit
    const isExpanded = el.dataset.style === 'expanded'
    const fetchUrl = el.dataset.referenceUrl
    const plural = el.dataset.labelPlural
    const emptyLabel = el.dataset.empty || 'None'
    const nameAttr = el.getAttribute('name')
    const required = el.required
    const origParent = el.parentNode
    const newUrl = el.dataset.newUrl
    const loader = document.createElement('ul')
    const loaderInner = document.createElement('li')
    loaderInner.classList.add('form-choice-label')
    loaderInner.appendChild(document.createTextNode(`Loading ${plural}...`))
    loader.classList.add('form-choice-list')
    loader.appendChild(loaderInner)


    const hidden = document.createElement('input')
    hidden.setAttribute('type', 'hidden')
    hidden.setAttribute('name', nameAttr)
    hidden.setAttribute('value', '');

    load()

    function load() {
        while(origParent.firstChild) {
            origParent.removeChild(origParent.lastChild)
        }
        origParent.appendChild(loader)

        window.fetch(fetchUrl).then((response) => {
            return response.json().then((json) => {
                if(isExpanded && json['nodes'].length < expandLimit || json['nodes'].length == 0) {
                    const options = document.createDocumentFragment();

                    options.appendChild(hidden)

                    if(isExplicit) {
                        const input = document.createElement('input')
                        input.setAttribute('value', '');
                        input.setAttribute('type', 'radio');
                        input.setAttribute('name', nameAttr)
                        if(oldValue == '') {
                            input.checked = true
                        }
                        const li = document.createElement('li')
                        li.classList.add('form-choice-item')
                        const label = document.createElement('label')
                        label.classList.add('form-choice-label')
                        const marker = document.createElement('i')
                        marker.classList.add('marker')

                        label.appendChild(input)
                        label.appendChild(document.createTextNode(emptyLabel))
                        label.appendChild(marker)

                        li.appendChild(label)
                        options.appendChild(li)
                    }

                    json['nodes'].forEach(node => {
                        const input = document.createElement('input')
                        input.setAttribute('value', node.nodeId);
                        input.setAttribute('type', 'radio');
                        input.setAttribute('name', nameAttr)
                        input.required = required
                        if(oldValue == node.nodeId) {
                            input.checked = true
                        }
                        const li = document.createElement('li')
                        li.classList.add('form-choice-item')
                        const label = document.createElement('label')
                        label.classList.add('form-choice-label')
                        const marker = document.createElement('i')
                        marker.classList.add('marker')

                        label.appendChild(input)
                        label.appendChild(document.createTextNode(node.label))
                        label.appendChild(marker)

                        li.appendChild(label)
                        options.appendChild(li)
                    })

                    const list = document.createElement('ul')
                    list.classList.add('form-choice-list')
                    list.appendChild(options)
                    if(!json['nodes'].length) {
                        const empty = document.createElement('li')
                        empty.appendChild(document.createTextNode(`No ${plural} yet`))
                        empty.classList.add('form-choice-info')

                        list.appendChild(empty)
                    }

                    if(newUrl) {
                        const newLink = document.createElement('a')
                        newLink.appendChild(document.createTextNode('Add new'))
                        newLink.href = newUrl
                        newLink.target='_blank'

                        const linkItem = document.createElement('li')
                        linkItem.classList.add('form-choice-info')

                        linkItem.appendChild(newLink)
                        list.appendChild(linkItem)
                    }

                    
                    const refreshLink = document.createElement('a')
                    refreshLink.href = '#'
                    refreshLink.appendChild(document.createTextNode('Refresh'))

                    const linkItem = document.createElement('li')
                    linkItem.classList.add('form-choice-info')

                    linkItem.appendChild(refreshLink)
                    list.appendChild(linkItem)
                    while(origParent.firstChild) {
                        origParent.removeChild(origParent.lastChild)
                    }
                    origParent.appendChild(list)
                    refreshLink.addEventListener('click', (e) => {load(); e.preventDefault()})

                } else {
                    const options = document.createDocumentFragment();

                
                    if(isExplicit) {
                        const nullOpt = document.createElement('option')
                        nullOpt.setAttribute('value', '');
                        nullOpt.appendChild(document.createTextNode(emptyLabel))
                        options.appendChild(nullOpt)
                    }

                    json['nodes'].forEach(node => {
                        const opt = document.createElement('option')
                        opt.setAttribute('value', node.nodeId);
                        opt.appendChild(document.createTextNode(node.label))
                        options.appendChild(opt)
                    })

                    const select = document.createElement('select')
                    select.setAttribute('name', nameAttr)
                    select.classList.add(...el.classList)
                    select.required = required;
                    select.appendChild(options)
                    select.value = oldValue;

                    while(origParent.firstChild) {
                        origParent.removeChild(origParent.lastChild)
                    }

                    origParent.appendChild(hidden)

                    if(!json['nodes'].length) {
                        const empty = document.createElement('ul')
                        const emptyInner = document.createElement('ul')
                        emptyInner.classList.add('form-choice-label')
                        emptyInner.appendChild(document.createTextNode(`No ${plural} yet`))
                        empty.classList.add('form-choice-list')
                        empty.appendChild(emptyInner)

                        origParent.appendChild(empty)
                    } else {
                        origParent.appendChild(select)
                    }

                    if(newUrl) {
                        const newLink = document.createElement('a')
                        newLink.appendChild(document.createTextNode('Add new'))
                        newLink.href = newUrl
                        newLink.target='_blank'

                        origParent.appendChild(newLink)
                        origParent.appendChild(document.createTextNode(' '))
                    }

                    {
                        const refreshLink = document.createElement('a')
                        refreshLink.href = '#'
                        refreshLink.appendChild(document.createTextNode('Refresh'))
                        
                        origParent.appendChild(refreshLink)

                        refreshLink.addEventListener('click', (e) => {load(); e.preventDefault()})
                    }
                }
            })
        }).catch((e) => {
            while(origParent.firstChild) {
                origParent.removeChild(origParent.lastChild)
            }
            origParent.appendChild(el)
        })
    }
}) 



Array.prototype.forEach.call(document.querySelectorAll('[data-autofill-by]'), (el) => {
    const source = document.getElementById(el.dataset.autofillBy)
    const suffix = el.dataset.autofillSuffix??''
    let active = !el.value

    if(source) {
        source.addEventListener('input', () => {
            if(active) {
                el.value = source.value + suffix
            }
        })

        el.addEventListener('input', () => {
            active = !el.value
        })
    }

})

Array.prototype.forEach.call(document.querySelectorAll('.form-field-sum-container'), (el) => {
    el.addEventListener('input', (evt) => {
        if(evt.target.classList.contains('nopointer')) {
            evt.preventDefault();
            evt.target.checked = evt.target.hasAttribute('checked')
            alert("a")
        } else if(evt.target.classList.contains('form-field-sum-radio') && evt.target.checked) {
            evt.target.nextElementSibling.querySelector('input').focus()
            console.log(evt.target.nextElementSibling.querySelector('input'))
        }
    })
})